<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PayoutService
{
    public function __construct(
        private WalletService $walletService,
        private AuditLogService $auditLogService,
        private NotificationService $notificationService,
        private PaymentConfigurationService $configuration,
        private PaymentGateway $gateway
    ) {}

    /**
     * Create a pending payout for a driver, drawn from their card wallet balance.
     * Amount defaults to the full available balance.
     */
    public function create(User $admin, User $driver, ?float $amount = null, ?string $note = null, string $method = 'manual'): Payout
    {
        if (! $driver->isRole('driver')) {
            throw new RuntimeException('Payouts can only be created for drivers.');
        }

        return DB::transaction(function () use ($admin, $driver, $amount, $note, $method) {
            $wallet = $this->walletService->createWalletForUser($driver);
            $available = (float) $wallet->wallet_balance;
            $amount = $amount ?? $available;

            if ($amount <= 0) {
                throw new RuntimeException('Payout amount must be greater than zero.');
            }

            if ($amount > $available) {
                throw new RuntimeException('Payout amount exceeds the driver wallet balance.');
            }

            $payout = Payout::create([
                'driver_id' => $driver->id,
                'amount' => round($amount, 2),
                'currency' => $wallet->currency,
                'status' => Payout::STATUS_PENDING,
                'method' => $method,
                'note' => $note,
            ]);

            $this->auditLogService->record($admin, 'payout_created', $payout, [], ['amount' => $payout->amount, 'driver_id' => $driver->id]);

            return $payout;
        });
    }

    /**
     * Confirm a pending payout: debit the driver wallet and write the ledger
     * entry. When Stripe Connect is enabled and the driver has a payout-ready
     * connected account (and no manual reference was provided), a real Stripe
     * transfer is executed and its id becomes the payout reference.
     */
    public function confirm(User $admin, Payout $payout, ?string $reference = null): Payout
    {
        if ($payout->status !== Payout::STATUS_PENDING) {
            throw new RuntimeException('Only pending payouts can be confirmed.');
        }

        $method = $payout->method;

        // External provider call happens before the DB transaction so a network
        // failure never leaves a committed-but-unsent payout. The idempotency
        // key makes a retry safe.
        if ($reference === null && $this->canUseProvider($payout->driver)) {
            $reference = $this->executeProviderTransfer($admin, $payout);
            $method = 'stripe';
        }

        return DB::transaction(function () use ($admin, $payout, $reference, $method) {
            $this->walletService->debitForPayout($payout->driver, (float) $payout->amount, $payout);

            $payout->update([
                'status' => Payout::STATUS_CONFIRMED,
                'method' => $method,
                'reference' => $reference,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
            ]);

            $this->auditLogService->record($admin, 'payout_confirmed', $payout, ['status' => Payout::STATUS_PENDING], ['status' => Payout::STATUS_CONFIRMED, 'reference' => $reference, 'method' => $method]);
            $this->notificationService->push($payout->driver, 'payout_confirmed', 'Payout sent', "A payout of {$payout->amount} {$payout->currency} has been confirmed.", ['screen' => 'wallet', 'severity' => 'success']);

            return $payout->fresh('driver');
        });
    }

    private function canUseProvider(User $driver): bool
    {
        if ($this->configuration->provider() !== 'stripe'
            || ! $this->configuration->enabled()
            || ! $this->configuration->connectEnabled()) {
            return false;
        }

        $account = $driver->paymentAccount;

        return $account !== null
            && ! empty($account->provider_account_id)
            && (bool) $account->payouts_enabled;
    }

    private function executeProviderTransfer(User $admin, Payout $payout): string
    {
        try {
            $transfer = $this->gateway->createTransfer([
                'amount' => (int) round((float) $payout->amount * 100),
                'currency' => strtolower($payout->currency),
                'destination' => $payout->driver->paymentAccount->provider_account_id,
                'metadata' => [
                    'mororide_payout_id' => (string) $payout->id,
                    'driver_id' => (string) $payout->driver_id,
                ],
            ], "mororide:payout:{$payout->id}:v1");
        } catch (Throwable $exception) {
            $payout->update([
                'status' => Payout::STATUS_FAILED,
                'note' => $this->appendNote($payout->note, 'Stripe transfer failed: '.$exception->getMessage()),
            ]);
            $this->auditLogService->record($admin, 'payout_failed', $payout, [], ['error' => $exception->getMessage()]);

            throw new RuntimeException('Stripe transfer failed: '.$exception->getMessage());
        }

        return (string) ($transfer['id'] ?? '');
    }

    private function appendNote(?string $existing, string $addition): string
    {
        return trim(($existing ? $existing.' | ' : '').$addition);
    }
}
