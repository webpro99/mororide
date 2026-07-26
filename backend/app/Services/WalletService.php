<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public const INSUFFICIENT_CASH_COMMISSION_CODE = 1001;

    public const INSUFFICIENT_CASH_COMMISSION_MESSAGE = 'Driver has no points or free rides available for cash commission.';

    public function createWalletForUser(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['points_balance' => 0, 'wallet_balance' => 0, 'free_rides_remaining' => 0, 'currency' => 'MAD']
        );
    }

    public function debitCashCommission(User $driver, Order $order, Transaction $transaction, float $fee): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($driver);

        if ((float) $wallet->points_balance >= $fee) {
            $wallet->points_balance = round((float) $wallet->points_balance - $fee, 2);
            $wallet->save();

            return $this->writeLedgerEntry($wallet, $order, $transaction, 'debit', 'cash_commission', $fee, -$fee, 'Cash ride platform fee deducted from driver points.');
        }

        if ($wallet->free_rides_remaining > 0) {
            $wallet->free_rides_remaining -= 1;
            $wallet->save();

            return $this->writeLedgerEntry($wallet, $order, $transaction, 'debit', 'free_ride_used', 0, 0, 'Free ride fallback used for cash commission.');
        }

        // OrderService performs the block after its completion transaction has
        // rolled back. Blocking here would be rolled back with the order and
        // transaction records that must not be committed on this failure.
        throw new RuntimeException(
            self::INSUFFICIENT_CASH_COMMISSION_MESSAGE,
            self::INSUFFICIENT_CASH_COMMISSION_CODE
        );
    }

    public function creditCardEarning(User $driver, Order $order, Transaction $transaction, float $net): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($driver);
        $wallet->wallet_balance = round((float) $wallet->wallet_balance + $net, 2);
        $wallet->save();

        return $this->writeLedgerEntry($wallet, $order, $transaction, 'credit', 'card_net_earning', $net, 0, 'Card ride net earning credited to driver wallet.');
    }

    public function waiveCashCommission(User $driver, Order $order, Transaction $transaction, string $reason): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($driver);

        return $this->writeLedgerEntry(
            $wallet,
            $order,
            $transaction,
            'debit',
            'cash_commission_waived',
            0,
            0,
            $reason,
            ['billing_off' => true]
        );
    }

    public function topUpPoints(User $driver, float $amount, ?PaymentIntent $paymentIntent = null): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($driver);
        $wallet->points_balance = round((float) $wallet->points_balance + $amount, 2);
        $wallet->save();

        return $this->writeLedgerEntry(
            $wallet,
            null,
            null,
            'credit',
            'points_topup',
            $amount,
            $amount,
            'Driver points top-up.',
            $paymentIntent ? ['provider' => $paymentIntent->provider] : [],
            $paymentIntent
        );
    }

    public function grantApprovalFreeRides(User $driver, int $rides = 2): Wallet
    {
        $wallet = $this->createWalletForUser($driver);

        $alreadyGranted = WalletLedgerEntry::where('user_id', $driver->id)
            ->where('entry_type', 'driver_approval_free_rides')
            ->exists();

        if ($alreadyGranted) {
            return $wallet;
        }

        $wallet->free_rides_remaining += $rides;
        $wallet->save();

        $this->writeLedgerEntry(
            $wallet,
            null,
            null,
            'credit',
            'driver_approval_free_rides',
            0,
            0,
            'Two free rides granted after driver approval.',
            ['free_rides_delta' => $rides, 'free_rides_after' => $wallet->free_rides_remaining]
        );

        return $wallet;
    }

    /**
     * Admin manual adjustment of points and/or wallet balance. Deltas may be
     * negative to debit. Always writes a ledger entry for auditability.
     */
    public function adjust(User $user, float $pointsDelta, float $balanceDelta, string $reason, array $metadata = []): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($user);

        $wallet->points_balance = round((float) $wallet->points_balance + $pointsDelta, 2);
        $wallet->wallet_balance = round((float) $wallet->wallet_balance + $balanceDelta, 2);

        if ($wallet->points_balance < 0 || $wallet->wallet_balance < 0) {
            throw new RuntimeException('Adjustment would make a wallet balance negative.');
        }

        $wallet->save();

        $direction = ($balanceDelta + $pointsDelta) >= 0 ? 'credit' : 'debit';

        return $this->writeLedgerEntry(
            $wallet,
            null,
            null,
            $direction,
            'manual_adjustment',
            abs($balanceDelta),
            $pointsDelta,
            $reason,
            $metadata
        );
    }

    /**
     * Debit a driver wallet when a payout is confirmed.
     */
    public function debitForPayout(User $driver, float $amount, Payout $payout): WalletLedgerEntry
    {
        $wallet = $this->createWalletForUser($driver);

        if ((float) $wallet->wallet_balance < $amount) {
            throw new RuntimeException('Payout amount exceeds the driver wallet balance.');
        }

        $wallet->wallet_balance = round((float) $wallet->wallet_balance - $amount, 2);
        $wallet->save();

        return $this->writeLedgerEntry(
            $wallet,
            null,
            null,
            'debit',
            'payout',
            $amount,
            0,
            'Payout confirmed.',
            ['payout_id' => $payout->id]
        );
    }

    /**
     * Reverse a card ride's driver net earning after a full refund or a
     * dispute. Idempotent per payment intent (guarded by the
     * payment_intent_id + entry_type unique index). The wallet is allowed to go
     * negative so the platform can recover the liability from future earnings;
     * any shortfall is recorded on the ledger entry for reconciliation.
     *
     * @return bool true when the driver-side liability is reconciled (nothing
     *              to reverse, already reversed, or reversal written); false
     *              when no matching transaction exists and manual review is
     *              still required.
     */
    public function reverseRideSettlement(PaymentIntent $paymentIntent, string $reason): bool
    {
        $transaction = Transaction::where('order_id', $paymentIntent->order_id)->first();

        if (! $transaction) {
            return false;
        }

        if ($transaction->type !== 'card' || ! $transaction->driver_id) {
            return true;
        }

        $alreadyReversed = WalletLedgerEntry::where('payment_intent_id', $paymentIntent->id)
            ->where('entry_type', 'refund')
            ->exists();

        if ($alreadyReversed) {
            return true;
        }

        $net = round((float) $transaction->net, 2);

        if ($net <= 0) {
            return true;
        }

        $driver = $transaction->driver;

        if (! $driver) {
            return false;
        }

        $wallet = $this->createWalletForUser($driver);
        $shortfall = max(0.0, round($net - (float) $wallet->wallet_balance, 2));
        $wallet->wallet_balance = round((float) $wallet->wallet_balance - $net, 2);
        $wallet->save();

        $this->writeLedgerEntry(
            $wallet,
            $transaction->order,
            $transaction,
            'debit',
            'refund',
            $net,
            0,
            $reason,
            $shortfall > 0 ? ['reversal' => true, 'liability_shortfall' => $shortfall] : ['reversal' => true],
            $paymentIntent
        );

        return true;
    }

    public function blockDriverIfNeeded(User $driver): void
    {
        DB::transaction(function () use ($driver) {
            $driver->update(['status' => 'blocked']);
            $driver->driverProfile?->update([
                'online_status' => false,
                'blocked_at' => now(),
            ]);
        });
    }

    public static function isInsufficientCashCommission(RuntimeException $exception): bool
    {
        return $exception->getCode() === self::INSUFFICIENT_CASH_COMMISSION_CODE
            && $exception->getMessage() === self::INSUFFICIENT_CASH_COMMISSION_MESSAGE;
    }

    public function writeLedgerEntry(
        Wallet $wallet,
        ?Order $order,
        ?Transaction $transaction,
        string $direction,
        string $entryType,
        float $amount,
        float $pointsDelta,
        string $reason,
        array $metadata = [],
        ?PaymentIntent $paymentIntent = null
    ): WalletLedgerEntry {
        return WalletLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'order_id' => $order?->id,
            'transaction_id' => $transaction?->id,
            'payment_intent_id' => $paymentIntent?->id,
            'direction' => $direction,
            'entry_type' => $entryType,
            'amount' => $amount,
            'points_delta' => $pointsDelta,
            'balance_after' => $wallet->wallet_balance,
            'points_after' => $wallet->points_balance,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
