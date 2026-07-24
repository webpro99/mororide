<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentIntent;
use App\Models\PaymentWebhookEvent;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private WalletService $walletService,
        private PaymentConfigurationService $configuration,
        private BillingModeService $billingModeService,
    ) {}

    public function createRidePayment(User $user, Order $order): array
    {
        if ($this->billingModeService->freeLaunchEnabled()) {
            throw new RuntimeException('Card payments are disabled while free launch mode is active.');
        }

        $this->ensureEnabled();

        if ($order->payment_method !== 'card') {
            throw new RuntimeException('This order is not configured for card payment.');
        }

        if (! in_array($order->status, [Order::STATUS_ASSIGNED, Order::STATUS_ARRIVED, Order::STATUS_IN_PROGRESS], true)) {
            throw new RuntimeException('A card payment can only be started after a driver is assigned.');
        }

        $amount = round((float) ($order->final_fare ?: $order->offered_fare), 2);
        $idempotencyKey = "mororide:ride:{$order->id}:v1";

        return $this->initiateIntent(
            $user,
            PaymentIntent::PURPOSE_RIDE,
            $amount,
            $idempotencyKey,
            $order,
            ['order_id' => (string) $order->id]
        );
    }

    public function createPointsTopup(User $driver, float $amount, string $clientIdempotencyKey): array
    {
        if ($this->billingModeService->freeLaunchEnabled()) {
            throw new RuntimeException('Driver wallet top-ups are disabled while free launch mode is active.');
        }

        $this->ensureEnabled();

        if (! $driver->isRole('driver')) {
            throw new RuntimeException('Only drivers can purchase points.');
        }

        $amount = round($amount, 2);
        $points = round($amount * $this->configuration->pointsPerCurrencyUnit(), 2);
        $idempotencyKey = 'mororide:points:'.$driver->id.':'.hash('sha256', $clientIdempotencyKey);

        $intent = $this->initiateIntent(
            $driver,
            PaymentIntent::PURPOSE_POINTS_TOPUP,
            $amount,
            $idempotencyKey,
            null,
            ['driver_id' => (string) $driver->id, 'points' => (string) $points]
        );

        return array_merge($intent, [
            'points' => $points,
            'point_price' => $this->configuration->pointPrice(),
        ]);
    }

    public function createConnectOnboarding(User $driver): array
    {
        $this->ensureConnectEnabled();

        if (! $driver->isRole('driver')) {
            throw new RuntimeException('Only drivers can create payout accounts.');
        }

        $account = PaymentAccount::firstOrCreate(
            ['user_id' => $driver->id],
            ['provider' => 'stripe', 'country' => $this->configuration->connectCountry(), 'status' => 'pending']
        );

        if (! $account->provider_account_id) {
            $providerAccount = $this->gateway->createConnectedAccount([
                'type' => 'express',
                'country' => $this->configuration->connectCountry(),
                'email' => $driver->email,
                'metadata' => ['mororide_user_id' => (string) $driver->id],
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ], "mororide:connect:driver:{$driver->id}:v1");

            $account->update(['provider_account_id' => $providerAccount['id']]);
            $account = $this->syncAccount($account, $providerAccount);
        }

        $link = $this->gateway->createAccountLink([
            'account' => $account->provider_account_id,
            'refresh_url' => $this->configuration->connectRefreshUrl(),
            'return_url' => $this->configuration->connectReturnUrl(),
            'type' => 'account_onboarding',
        ]);

        return [
            'account' => $this->accountPayload($account),
            'onboarding_url' => $link['url'],
            'expires_at' => $link['expires_at'] ?? null,
        ];
    }

    public function getConnectStatus(User $driver): array
    {
        $this->ensureConnectEnabled();

        $account = PaymentAccount::where('user_id', $driver->id)->first();

        if (! $account?->provider_account_id) {
            return $this->accountPayload($account ?? new PaymentAccount([
                'user_id' => $driver->id,
                'provider' => 'stripe',
                'country' => $this->configuration->connectCountry(),
                'status' => 'not_started',
            ]));
        }

        return $this->accountPayload($this->syncAccount(
            $account,
            $this->gateway->retrieveConnectedAccount($account->provider_account_id)
        ));
    }

    public function requestRefund(PaymentIntent $intent, ?float $amount, string $reason, string $clientIdempotencyKey): array
    {
        $this->ensureEnabled();

        $requestHash = hash('sha256', $clientIdempotencyKey);
        $existingMetadata = $intent->provider_metadata ?? [];
        $alreadyRefundedMinor = (int) ($existingMetadata['amount_refunded_minor'] ?? 0);
        $requestedMinor = $amount === null
            ? $intent->amount_minor - $alreadyRefundedMinor
            : $this->toMinor(round($amount, 2));
        if ($intent->status === 'refund_pending'
            && ($existingMetadata['latest_refund_request_hash'] ?? null) === $requestHash) {
            if ((int) ($existingMetadata['latest_refund_amount_minor'] ?? -1) !== $requestedMinor
                || ($existingMetadata['latest_refund_reason'] ?? null) !== $reason) {
                throw new RuntimeException('The idempotency key was already used for different refund parameters.');
            }

            return [
                'payment_intent' => $this->publicIntentPayload($intent),
                'refund' => [
                    'id' => $existingMetadata['latest_refund_id'] ?? null,
                    'status' => $existingMetadata['latest_refund_status'] ?? 'pending',
                    'amount_minor' => $existingMetadata['latest_refund_amount_minor'] ?? null,
                    'currency' => $existingMetadata['latest_refund_currency'] ?? $intent->currency,
                ],
            ];
        }

        if (! $intent->provider_intent_id || ! in_array($intent->status, ['succeeded', 'partially_refunded'], true)) {
            throw new RuntimeException('Only a succeeded Stripe payment can be refunded.');
        }

        $parameters = [
            'payment_intent' => $intent->provider_intent_id,
            'reason' => $reason,
            'metadata' => ['mororide_payment_intent_id' => (string) $intent->id],
        ];

        if ($amount !== null) {
            $amount = round($amount, 2);
            $remaining = $intent->amount_minor - $alreadyRefundedMinor;
            if ($amount <= 0 || $this->toMinor($amount) > $remaining) {
                throw new RuntimeException('Refund amount must be positive and cannot exceed the remaining payment amount.');
            }
            $parameters['amount'] = $this->toMinor($amount);
        }

        $refund = $this->gateway->createRefund(
            $parameters,
            'mororide:refund:'.$intent->id.':'.$requestHash
        );

        $metadata = $intent->provider_metadata ?? [];
        $metadata['latest_refund_id'] = $refund['id'];
        $metadata['latest_refund_status'] = $refund['status'] ?? 'pending';
        $metadata['latest_refund_amount_minor'] = $refund['amount'] ?? $requestedMinor;
        $metadata['latest_refund_currency'] = $refund['currency'] ?? $intent->currency;
        $metadata['latest_refund_request_hash'] = $requestHash;
        $metadata['latest_refund_reason'] = $reason;
        $intent->update(['status' => 'refund_pending', 'provider_metadata' => $metadata]);

        return [
            'payment_intent' => $this->publicIntentPayload($intent->fresh()),
            'refund' => [
                'id' => $refund['id'],
                'status' => $refund['status'] ?? null,
                'amount_minor' => $refund['amount'] ?? null,
                'currency' => $refund['currency'] ?? $intent->currency,
            ],
        ];
    }

    /**
     * Process a signature-verified Stripe event. The provider event ID and
     * payment/ledger uniqueness make retries safe.
     */
    public function handleWebhook(array $event, string $rawPayload): bool
    {
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($eventId === '' || $type === '') {
            throw new RuntimeException('Stripe event is missing its id or type.');
        }

        $webhook = PaymentWebhookEvent::firstOrCreate(
            ['provider_event_id' => $eventId],
            [
                'provider' => 'stripe',
                'type' => $type,
                'livemode' => (bool) ($event['livemode'] ?? false),
                'payload_hash' => hash('sha256', $rawPayload),
                'status' => 'processing',
            ]
        );

        if ($webhook->status === 'processed' || $webhook->status === 'ignored') {
            return false;
        }

        try {
            return DB::transaction(function () use ($webhook, $event, $type) {
                $locked = PaymentWebhookEvent::lockForUpdate()->findOrFail($webhook->id);

                if (in_array($locked->status, ['processed', 'ignored'], true)) {
                    return false;
                }

                $locked->increment('attempts');
                $handled = match ($type) {
                    'payment_intent.succeeded' => $this->handlePaymentSucceeded($event),
                    'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
                    'charge.refunded' => $this->handleChargeRefunded($event),
                    'charge.dispute.created', 'charge.dispute.closed' => $this->handleDispute($event),
                    'account.updated' => $this->handleAccountUpdated($event),
                    'transfer.reversed' => $this->handleTransferReversed($event),
                    default => false,
                };

                $locked->update([
                    'status' => $handled ? 'processed' : 'ignored',
                    'error' => null,
                    'processed_at' => now(),
                ]);

                return $handled;
            });
        } catch (Throwable $exception) {
            $webhook->refresh()->update([
                'status' => 'failed',
                'attempts' => $webhook->attempts + 1,
                'error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }

    public function hasSucceededRidePayment(Order $order, float $fare, bool $lock = false): ?PaymentIntent
    {
        $query = PaymentIntent::query()
            ->where('order_id', $order->id)
            ->where('purpose', PaymentIntent::PURPOSE_RIDE)
            ->where('status', 'succeeded')
            ->where('amount_minor', $this->toMinor($fare));

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function initiateIntent(
        User $user,
        string $purpose,
        float $amount,
        string $idempotencyKey,
        ?Order $order,
        array $metadata
    ): array {
        $currency = $this->configuration->currency();
        $minor = $this->toMinor($amount);

        $intent = PaymentIntent::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $user->id,
                'order_id' => $order?->id,
                'provider' => 'stripe',
                'purpose' => $purpose,
                'amount' => $amount,
                'amount_minor' => $minor,
                'currency' => $currency,
                'status' => 'initiating',
                'provider_metadata' => $metadata,
            ]
        );

        if ($intent->user_id !== $user->id || $intent->amount_minor !== $minor || $intent->purpose !== $purpose) {
            throw new RuntimeException('The idempotency key was already used for different payment parameters.');
        }

        $providerIntent = $intent->provider_intent_id
            ? $this->gateway->retrievePaymentIntent($intent->provider_intent_id)
            : $this->gateway->createPaymentIntent([
                'amount' => $minor,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $user->email,
                'description' => $purpose === PaymentIntent::PURPOSE_RIDE
                    ? "MoroRide order #{$order->id}"
                    : 'MoroRide driver points top-up',
                'metadata' => array_merge($metadata, [
                    'mororide_payment_intent_id' => (string) $intent->id,
                    'purpose' => $purpose,
                ]),
            ], $idempotencyKey);

        $providerMetadata = array_merge($intent->provider_metadata ?? [], [
            'provider_status' => $providerIntent['status'] ?? null,
        ]);

        $providerStatus = (string) ($providerIntent['status'] ?? 'requires_payment_method');
        // A client refresh may retrieve a remote `succeeded` status, but only
        // the signed webhook is allowed to unlock fulfillment in our database.
        $localStatus = $intent->status === 'succeeded'
            ? 'succeeded'
            : ($providerStatus === 'succeeded' ? 'processing' : $providerStatus);

        $intent->update([
            'provider_intent_id' => $providerIntent['id'],
            'status' => $localStatus,
            'provider_metadata' => $providerMetadata,
            'failure_code' => null,
            'failure_message' => null,
        ]);

        return array_merge($this->publicIntentPayload($intent->fresh()), [
            'client_secret' => $providerIntent['client_secret'] ?? null,
            'publishable_key' => $this->configuration->publishableKey(),
        ]);
    }

    private function handlePaymentSucceeded(array $event): bool
    {
        $object = Arr::get($event, 'data.object', []);
        $providerIntentId = $object['id'] ?? null;
        if (! is_string($providerIntentId) || $providerIntentId === '') {
            return false;
        }

        $intent = PaymentIntent::where('provider_intent_id', $providerIntentId)->lockForUpdate()->first();

        if (! $intent) {
            return false;
        }

        $wasSucceeded = $intent->status === 'succeeded';
        $metadata = array_merge($intent->provider_metadata ?? [], [
            'latest_charge' => $object['latest_charge'] ?? null,
            'provider_status' => $object['status'] ?? 'succeeded',
        ]);

        $intent->update([
            'status' => 'succeeded',
            'succeeded_at' => $intent->succeeded_at ?? now(),
            'failure_code' => null,
            'failure_message' => null,
            'provider_metadata' => $metadata,
        ]);

        if (! $wasSucceeded && $intent->purpose === PaymentIntent::PURPOSE_POINTS_TOPUP) {
            $alreadyCredited = WalletLedgerEntry::where('payment_intent_id', $intent->id)
                ->where('entry_type', 'points_topup')
                ->exists();

            if (! $alreadyCredited) {
                $points = (float) ($metadata['points'] ?? ((float) $intent->amount * $this->configuration->pointsPerCurrencyUnit()));
                $this->walletService->topUpPoints($intent->user, $points, $intent);
            }
        }

        return true;
    }

    private function handlePaymentFailed(array $event): bool
    {
        $object = Arr::get($event, 'data.object', []);
        $providerIntentId = $object['id'] ?? null;
        if (! is_string($providerIntentId) || $providerIntentId === '') {
            return false;
        }

        $intent = PaymentIntent::where('provider_intent_id', $providerIntentId)->lockForUpdate()->first();

        if (! $intent || $intent->status === 'succeeded') {
            return (bool) $intent;
        }

        $error = $object['last_payment_error'] ?? [];
        $intent->update([
            'status' => 'payment_failed',
            'failure_code' => $error['code'] ?? null,
            'failure_message' => $error['message'] ?? 'Stripe reported that the payment failed.',
        ]);

        return true;
    }

    private function handleChargeRefunded(array $event): bool
    {
        $charge = Arr::get($event, 'data.object', []);
        $providerIntentId = $charge['payment_intent'] ?? null;
        if (! is_string($providerIntentId) || $providerIntentId === '') {
            return false;
        }

        $intent = PaymentIntent::where('provider_intent_id', $providerIntentId)->lockForUpdate()->first();

        if (! $intent) {
            return false;
        }

        $refundedMinor = (int) ($charge['amount_refunded'] ?? 0);
        $isFull = $refundedMinor >= $intent->amount_minor;

        // A full refund reverses the driver's net earning automatically; a
        // partial refund is ambiguous and still needs manual reconciliation.
        $reconciled = false;
        if ($intent->order_id && $isFull) {
            $reconciled = $this->walletService->reverseRideSettlement(
                $intent,
                'Card ride fully refunded — driver net reversed.'
            );
        }

        $metadata = array_merge($intent->provider_metadata ?? [], [
            'amount_refunded_minor' => $refundedMinor,
            'refund_reconciliation_required' => ! $reconciled,
        ]);

        $intent->update([
            'status' => $isFull ? 'refunded' : 'partially_refunded',
            'refunded_at' => $isFull ? now() : null,
            'provider_metadata' => $metadata,
        ]);

        if ($intent->order_id) {
            $this->updateRideTransactionStatus($intent, $isFull ? 'refunded' : 'partially_refunded', $reconciled);
        }

        return true;
    }

    private function handleDispute(array $event): bool
    {
        $dispute = Arr::get($event, 'data.object', []);
        $providerIntent = $dispute['payment_intent'] ?? null;
        $providerIntentId = is_array($providerIntent) ? ($providerIntent['id'] ?? null) : $providerIntent;
        if (! is_string($providerIntentId) || $providerIntentId === '') {
            return false;
        }

        $intent = PaymentIntent::where('provider_intent_id', $providerIntentId)->lockForUpdate()->first();

        if (! $intent) {
            return false;
        }

        $eventType = (string) $event['type'];
        $status = $eventType === 'charge.dispute.created'
            ? 'disputed'
            : 'dispute_'.($dispute['status'] ?? 'closed');

        // When a dispute is opened, or lost on close, the driver's net earning
        // is reversed. A dispute won (status "won") leaves the earning intact.
        $disputeStatus = (string) ($dispute['status'] ?? '');
        $shouldReverse = $eventType === 'charge.dispute.created'
            || ($eventType === 'charge.dispute.closed' && $disputeStatus === 'lost');

        $reconciled = false;
        if ($intent->order_id && $shouldReverse) {
            $reconciled = $this->walletService->reverseRideSettlement(
                $intent,
                'Card ride disputed — driver net reversed.'
            );
        } elseif ($eventType === 'charge.dispute.closed' && $disputeStatus === 'won') {
            $reconciled = true;
        }

        $metadata = array_merge($intent->provider_metadata ?? [], [
            'dispute_id' => $dispute['id'] ?? null,
            'dispute_status' => $dispute['status'] ?? null,
            'dispute_reconciliation_required' => ! $reconciled,
        ]);

        $intent->update(['status' => $status, 'provider_metadata' => $metadata]);
        if ($intent->order_id) {
            $this->updateRideTransactionStatus($intent, $status, $reconciled);
        }

        return true;
    }

    /**
     * A reversed transfer means a confirmed driver payout was clawed back by
     * Stripe. Re-credit the driver wallet and mark the payout failed. Matched
     * by the transfer id stored as the payout reference at confirmation time.
     */
    private function handleTransferReversed(array $event): bool
    {
        $transfer = Arr::get($event, 'data.object', []);
        $transferId = $transfer['id'] ?? null;
        if (! is_string($transferId) || $transferId === '') {
            return false;
        }

        $payout = Payout::where('reference', $transferId)->lockForUpdate()->first();

        if (! $payout) {
            return false;
        }

        if ($payout->status === Payout::STATUS_FAILED) {
            return true;
        }

        $this->walletService->adjust(
            $payout->driver,
            0,
            (float) $payout->amount,
            'Payout transfer reversed by Stripe.',
            ['payout_id' => $payout->id, 'transfer_id' => $transferId]
        );

        $payout->update([
            'status' => Payout::STATUS_FAILED,
            'note' => trim(($payout->note ? $payout->note.' | ' : '').'Stripe reversed the transfer.'),
        ]);

        return true;
    }

    private function handleAccountUpdated(array $event): bool
    {
        $providerAccount = Arr::get($event, 'data.object', []);
        $providerAccountId = $providerAccount['id'] ?? null;
        if (! is_string($providerAccountId) || $providerAccountId === '') {
            return false;
        }

        $account = PaymentAccount::where('provider_account_id', $providerAccountId)->lockForUpdate()->first();

        if (! $account) {
            return false;
        }

        $this->syncAccount($account, $providerAccount);

        return true;
    }

    private function syncAccount(PaymentAccount $account, array $providerAccount): PaymentAccount
    {
        $charges = (bool) ($providerAccount['charges_enabled'] ?? false);
        $payouts = (bool) ($providerAccount['payouts_enabled'] ?? false);
        $details = (bool) ($providerAccount['details_submitted'] ?? false);
        $disabledReason = Arr::get($providerAccount, 'requirements.disabled_reason');
        $status = $charges && $payouts && $details
            ? 'ready'
            : ($disabledReason ? 'restricted' : 'pending');

        $account->update([
            'country' => $providerAccount['country'] ?? $account->country,
            'status' => $status,
            'charges_enabled' => $charges,
            'payouts_enabled' => $payouts,
            'details_submitted' => $details,
            'capabilities' => $providerAccount['capabilities'] ?? null,
            'requirements' => $providerAccount['requirements'] ?? null,
            'onboarding_completed_at' => $status === 'ready'
                ? ($account->onboarding_completed_at ?? now())
                : null,
        ]);

        return $account->fresh();
    }

    private function updateRideTransactionStatus(PaymentIntent $intent, string $status, bool $reconciled): void
    {
        $transaction = Transaction::where('order_id', $intent->order_id)->lockForUpdate()->first();
        if (! $transaction) {
            return;
        }

        $metadata = array_merge($transaction->metadata ?? [], [
            'payment_state' => $status,
            'payment_reconciliation_required' => ! $reconciled,
        ]);
        $transaction->update(['status' => $status, 'metadata' => $metadata]);
    }

    private function publicIntentPayload(PaymentIntent $intent): array
    {
        return [
            'id' => $intent->id,
            'provider' => $intent->provider,
            'provider_intent_id' => $intent->provider_intent_id,
            'purpose' => $intent->purpose,
            'order_id' => $intent->order_id,
            'amount' => (float) $intent->amount,
            'amount_minor' => $intent->amount_minor,
            'currency' => $intent->currency,
            'status' => $intent->status,
            'succeeded_at' => $intent->succeeded_at,
        ];
    }

    private function accountPayload(PaymentAccount $account): array
    {
        return [
            'provider' => $account->provider,
            'account_id' => $account->provider_account_id,
            'country' => $account->country,
            'status' => $account->status,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'requirements' => $account->requirements,
        ];
    }

    private function ensureEnabled(): void
    {
        if ($this->configuration->provider() !== 'stripe' || ! $this->configuration->enabled()) {
            throw new RuntimeException('Stripe payments are disabled. Enable them from Admin Dashboard > Payments.', 503);
        }

        if (! $this->configuration->credentialsReady()) {
            throw new RuntimeException('Stripe payments are incomplete. Add the required keys in Admin Dashboard > Payments.', 503);
        }
    }

    private function ensureConnectEnabled(): void
    {
        $this->ensureEnabled();

        if (! $this->configuration->connectEnabled()) {
            throw new RuntimeException('Stripe Connect is disabled for this deployment.', 503);
        }

        if (! preg_match('/^[A-Z]{2}$/', $this->configuration->connectCountry())) {
            throw new RuntimeException('Configure a supported two-letter Stripe Connect country in Admin Dashboard > Payments.', 503);
        }
    }

    private function toMinor(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
