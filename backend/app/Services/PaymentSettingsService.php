<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\PaymentWebhookEvent;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentSettingsService
{
    public const WEBHOOK_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'charge.refunded',
        'charge.dispute.created',
        'charge.dispute.closed',
        'account.updated',
    ];

    public function __construct(
        private PaymentConfigurationService $configuration,
        private AuditLogService $auditLogs,
        private PaymentGateway $gateway,
    ) {}

    public function adminPayload(string $webhookUrl): array
    {
        $this->ensureDefaults();

        $latestWebhook = PaymentWebhookEvent::latest('id')->first();

        return [
            'values' => [
                'provider' => $this->configuration->provider(),
                'enabled' => $this->configuration->enabled(),
                'mode' => $this->configuration->mode(),
                'publishable_key' => $this->configuration->publishableKey(),
                'currency' => strtoupper($this->configuration->currency()),
                'points_min_topup' => $this->configuration->pointsMinTopup(),
                'points_max_topup' => $this->configuration->pointsMaxTopup(),
                'points_per_currency_unit' => $this->configuration->pointsPerCurrencyUnit(),
                'point_price' => $this->configuration->pointPrice(),
                'connect_enabled' => $this->configuration->connectEnabled(),
                'connect_country' => $this->configuration->connectCountry(),
                'connect_refresh_url' => $this->configuration->connectRefreshUrl(),
                'connect_return_url' => $this->configuration->connectReturnUrl(),
            ],
            'status' => [
                'ready' => $this->configuration->ready(),
                'credentials_ready' => $this->configuration->credentialsReady(),
                'source' => $this->configuration->source(),
                'missing_fields' => $this->configuration->missingFields(),
                'secret_key_configured' => $this->configuration->secretKey() !== null,
                'secret_key_masked' => $this->mask($this->configuration->secretKey()),
                'webhook_secret_configured' => $this->configuration->webhookSecret() !== null,
                'webhook_secret_masked' => $this->mask($this->configuration->webhookSecret()),
            ],
            'webhook' => [
                'url' => $webhookUrl,
                'events' => self::WEBHOOK_EVENTS,
                'latest_status' => $latestWebhook?->status,
                'latest_received_at' => $latestWebhook?->created_at,
                'failed_count' => PaymentWebhookEvent::where('status', 'failed')->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $values */
    public function update(array $values, User $actor, string $webhookUrl): array
    {
        $this->ensureDefaults();

        $clear = $values['clear_secrets'] ?? [];
        $secretKey = in_array('secret_key', $clear, true)
            ? null
            : $this->newOrCurrentSecret($values['secret_key'] ?? null, $this->configuration->secretKey());
        $webhookSecret = in_array('webhook_secret', $clear, true)
            ? null
            : $this->newOrCurrentSecret($values['webhook_secret'] ?? null, $this->configuration->webhookSecret());

        $normalized = [
            'provider' => strtolower((string) $values['provider']),
            'enabled' => (bool) $values['enabled'],
            'mode' => strtolower((string) $values['mode']),
            'publishable_key' => trim((string) ($values['publishable_key'] ?? '')) ?: null,
            'secret_key' => $secretKey,
            'webhook_secret' => $webhookSecret,
            'currency' => strtolower((string) $values['currency']),
            'points_min_topup' => (float) $values['points_min_topup'],
            'points_max_topup' => (float) $values['points_max_topup'],
            'points_per_currency_unit' => isset($values['point_price'])
                ? round(1 / (float) $values['point_price'], 8)
                : (float) $values['points_per_currency_unit'],
            'connect_enabled' => (bool) $values['connect_enabled'],
            'connect_country' => strtoupper(trim((string) ($values['connect_country'] ?? ''))),
            'connect_refresh_url' => trim((string) ($values['connect_refresh_url'] ?? '')),
            'connect_return_url' => trim((string) ($values['connect_return_url'] ?? '')),
        ];

        if ($normalized['provider'] === 'cash_only') {
            $normalized['enabled'] = false;
        }
        if (! $normalized['enabled']) {
            $normalized['connect_enabled'] = false;
        }

        $this->validateBusinessRules($normalized);

        DB::transaction(function () use ($normalized): void {
            $this->save('payment.provider', $normalized['provider']);
            $this->save('payment.stripe_enabled', $normalized['enabled'] ? '1' : '0');
            $this->save('payment.stripe_mode', $normalized['mode']);
            $this->save('payment.stripe_publishable_key', $normalized['publishable_key']);
            $this->saveSecret('payment.stripe_secret_key', $normalized['secret_key']);
            $this->saveSecret('payment.stripe_webhook_secret', $normalized['webhook_secret']);
            $this->save('payment.currency', $normalized['currency']);
            $this->save('payment.points_min_topup', (string) $normalized['points_min_topup']);
            $this->save('payment.points_max_topup', (string) $normalized['points_max_topup']);
            $this->save('payment.points_per_currency_unit', (string) $normalized['points_per_currency_unit']);
            $this->save('payment.stripe_connect_enabled', $normalized['connect_enabled'] ? '1' : '0');
            $this->save('payment.stripe_connect_country', $normalized['connect_country']);
            $this->save('payment.stripe_connect_refresh_url', $normalized['connect_refresh_url']);
            $this->save('payment.stripe_connect_return_url', $normalized['connect_return_url']);
        });

        $this->auditLogs->record($actor, 'payment_settings_updated', null, [], [
            'provider' => $normalized['provider'],
            'enabled' => $normalized['enabled'],
            'mode' => $normalized['mode'],
            'currency' => $normalized['currency'],
            'points_min_topup' => $normalized['points_min_topup'],
            'points_max_topup' => $normalized['points_max_topup'],
            'points_per_currency_unit' => $normalized['points_per_currency_unit'],
            'point_price' => round(1 / $normalized['points_per_currency_unit'], 4),
            'connect_enabled' => $normalized['connect_enabled'],
            'connect_country' => $normalized['connect_country'],
            'secret_key_configured' => $secretKey !== null,
            'webhook_secret_configured' => $webhookSecret !== null,
        ]);

        return $this->adminPayload($webhookUrl);
    }

    public function testConnection(User $actor): array
    {
        $this->ensureDefaults();

        if ($this->configuration->secretKey() === null) {
            throw ValidationException::withMessages([
                'secret_key' => 'Save a Stripe secret key before testing the connection.',
            ]);
        }

        try {
            $result = $this->gateway->verifyConnection();
        } catch (Throwable $exception) {
            report($exception);
            $this->auditLogs->record($actor, 'payment_connection_test_failed', null, [], [
                'provider' => 'stripe',
                'mode' => $this->configuration->mode(),
            ]);

            throw ValidationException::withMessages([
                'connection' => 'Stripe connection failed. Check the saved secret key and its account access.',
            ]);
        }

        $safe = [
            'connected' => true,
            'livemode' => (bool) ($result['livemode'] ?? false),
            'available_currencies' => array_values(array_unique($result['available_currencies'] ?? [])),
        ];

        $this->auditLogs->record($actor, 'payment_connection_test_succeeded', null, [], $safe);

        return $safe;
    }

    public function ensureDefaults(): void
    {
        $mode = (string) config('stripe.mode', 'test');
        $publishable = config('stripe.publishable_key');
        if (is_string($publishable) && str_starts_with($publishable, 'pk_live_')) {
            $mode = 'live';
        }

        $definitions = [
            ['payment.provider', 'stripe', 'string', 'Payment provider'],
            ['payment.stripe_enabled', config('stripe.enabled', false) ? '1' : '0', 'boolean', 'Enable Stripe payments'],
            ['payment.stripe_mode', $mode, 'string', 'Stripe mode'],
            ['payment.stripe_publishable_key', config('stripe.publishable_key'), 'string', 'Stripe publishable key'],
            ['payment.stripe_secret_key', $this->encryptedDefault(config('stripe.secret_key')), 'secret', 'Stripe secret key'],
            ['payment.stripe_webhook_secret', $this->encryptedDefault(config('stripe.webhook_secret')), 'secret', 'Stripe webhook signing secret'],
            ['payment.currency', strtolower((string) config('stripe.currency', 'mad')), 'string', 'Payment currency'],
            ['payment.points_min_topup', (string) config('stripe.points.min_topup', 20), 'number', 'Minimum points top-up'],
            ['payment.points_max_topup', (string) config('stripe.points.max_topup', 5000), 'number', 'Maximum points top-up'],
            ['payment.points_per_currency_unit', (string) config('stripe.points.points_per_currency_unit', 1), 'number', 'Points per currency unit'],
            ['payment.stripe_connect_enabled', config('stripe.connect.enabled', false) ? '1' : '0', 'boolean', 'Enable Stripe Connect'],
            ['payment.stripe_connect_country', strtoupper((string) config('stripe.connect.country', '')), 'string', 'Stripe Connect country'],
            ['payment.stripe_connect_refresh_url', config('stripe.connect.refresh_url'), 'string', 'Stripe Connect refresh URL'],
            ['payment.stripe_connect_return_url', config('stripe.connect.return_url'), 'string', 'Stripe Connect return URL'],
        ];

        foreach ($definitions as [$key, $value, $type, $label]) {
            PlatformSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => PaymentConfigurationService::GROUP, 'label' => $label]
            );
        }
    }

    /** @param array<string, mixed> $values */
    private function validateBusinessRules(array $values): void
    {
        $errors = [];
        $prefix = $values['mode'] === 'live' ? 'live' : 'test';

        if ($values['points_max_topup'] < $values['points_min_topup']) {
            $errors['points_max_topup'] = 'Maximum top-up must be greater than or equal to the minimum.';
        }
        if ($values['publishable_key'] !== null && ! str_starts_with($values['publishable_key'], "pk_{$prefix}_")) {
            $errors['publishable_key'] = "The publishable key must match {$values['mode']} mode.";
        }
        if ($values['secret_key'] !== null && ! str_starts_with($values['secret_key'], "sk_{$prefix}_")) {
            $errors['secret_key'] = "The secret key must match {$values['mode']} mode.";
        }
        if ($values['webhook_secret'] !== null && ! str_starts_with($values['webhook_secret'], 'whsec_')) {
            $errors['webhook_secret'] = 'The webhook signing secret must start with whsec_.';
        }

        if ($values['provider'] === 'stripe' && $values['enabled']) {
            foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $key) {
                if ($values[$key] === null) {
                    $errors[$key] = 'This value is required before Stripe can be enabled.';
                }
            }
        }

        if ($values['connect_enabled']) {
            if (! $values['enabled'] || $values['provider'] !== 'stripe') {
                $errors['connect_enabled'] = 'Stripe Connect requires enabled Stripe payments.';
            }
            if (! preg_match('/^[A-Z]{2}$/', $values['connect_country'])) {
                $errors['connect_country'] = 'Enter a two-letter Stripe account country code.';
            }
            if ($values['connect_refresh_url'] === '' || $values['connect_return_url'] === '') {
                $errors['connect_refresh_url'] = 'Both Stripe Connect callback URLs are required.';
            }
        }

        if ($values['mode'] === 'live') {
            foreach (['connect_refresh_url', 'connect_return_url'] as $key) {
                if ($values[$key] !== '' && ! str_starts_with(strtolower($values[$key]), 'https://')) {
                    $errors[$key] = 'Live mode callback URLs must use HTTPS.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function save(string $key, mixed $value): void
    {
        PlatformSetting::where('key', $key)
            ->where('group', PaymentConfigurationService::GROUP)
            ->update(['value' => $value]);
    }

    private function saveSecret(string $key, ?string $value): void
    {
        $this->save($key, $value === null ? null : Crypt::encryptString($value));
    }

    private function encryptedDefault(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? Crypt::encryptString(trim($value)) : null;
    }

    private function newOrCurrentSecret(mixed $new, ?string $current): ?string
    {
        return is_string($new) && trim($new) !== '' ? trim($new) : $current;
    }

    private function mask(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, min(7, strlen($value))).'••••'.substr($value, -4);
    }
}
