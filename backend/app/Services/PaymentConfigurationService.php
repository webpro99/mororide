<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class PaymentConfigurationService
{
    public const GROUP = 'payments';

    public function provider(): string
    {
        return (string) $this->value('payment.provider', 'stripe');
    }

    public function enabled(): bool
    {
        return $this->boolean('payment.stripe_enabled', (bool) config('stripe.enabled', false));
    }

    public function mode(): string
    {
        return (string) $this->value('payment.stripe_mode', config('stripe.mode', 'test'));
    }

    public function publishableKey(): ?string
    {
        return $this->nullableString($this->value('payment.stripe_publishable_key', config('stripe.publishable_key')));
    }

    public function secretKey(): ?string
    {
        return $this->secret('payment.stripe_secret_key', config('stripe.secret_key'));
    }

    public function webhookSecret(): ?string
    {
        return $this->secret('payment.stripe_webhook_secret', config('stripe.webhook_secret'));
    }

    public function webhookTolerance(): int
    {
        return max(30, (int) config('stripe.webhook_tolerance', 300));
    }

    public function currency(): string
    {
        return strtolower((string) $this->value('payment.currency', config('stripe.currency', 'mad')));
    }

    public function pointsMinTopup(): float
    {
        return (float) $this->value('payment.points_min_topup', config('stripe.points.min_topup', 20));
    }

    public function pointsMaxTopup(): float
    {
        return (float) $this->value('payment.points_max_topup', config('stripe.points.max_topup', 5000));
    }

    public function pointsPerCurrencyUnit(): float
    {
        return (float) $this->value('payment.points_per_currency_unit', config('stripe.points.points_per_currency_unit', 1));
    }

    public function pointPrice(): float
    {
        $pointsPerCurrencyUnit = $this->pointsPerCurrencyUnit();

        return $pointsPerCurrencyUnit > 0 ? round(1 / $pointsPerCurrencyUnit, 4) : 1.0;
    }

    /** @return array<string, mixed> */
    public function pointsPurchaseConfig(): array
    {
        $minimum = $this->pointsMinTopup();
        $maximum = $this->pointsMaxTopup();
        $ratio = $this->pointsPerCurrencyUnit();

        return [
            'available' => $this->provider() === 'stripe' && $this->enabled() && $this->credentialsReady(),
            'currency' => strtoupper($this->currency()),
            'point_price' => $this->pointPrice(),
            'points_per_currency_unit' => $ratio,
            'minimum_payment' => $minimum,
            'maximum_payment' => $maximum,
            'minimum_points' => round($minimum * $ratio, 2),
            'maximum_points' => round($maximum * $ratio, 2),
        ];
    }

    public function connectEnabled(): bool
    {
        return $this->boolean('payment.stripe_connect_enabled', (bool) config('stripe.connect.enabled', false));
    }

    public function connectCountry(): string
    {
        return strtoupper((string) $this->value('payment.stripe_connect_country', config('stripe.connect.country', '')));
    }

    public function connectRefreshUrl(): string
    {
        return (string) $this->value('payment.stripe_connect_refresh_url', config('stripe.connect.refresh_url', ''));
    }

    public function connectReturnUrl(): string
    {
        return (string) $this->value('payment.stripe_connect_return_url', config('stripe.connect.return_url', ''));
    }

    public function credentialsReady(): bool
    {
        return $this->publishableKey() !== null
            && $this->secretKey() !== null
            && $this->webhookSecret() !== null;
    }

    public function ready(): bool
    {
        return $this->provider() === 'stripe' && $this->enabled() && $this->credentialsReady();
    }

    /** @return array<int, string> */
    public function missingFields(): array
    {
        $missing = [];

        if ($this->publishableKey() === null) {
            $missing[] = 'publishable_key';
        }
        if ($this->secretKey() === null) {
            $missing[] = 'secret_key';
        }
        if ($this->webhookSecret() === null) {
            $missing[] = 'webhook_secret';
        }

        return $missing;
    }

    public function source(): string
    {
        return PlatformSetting::where('group', self::GROUP)->exists() ? 'dashboard' : 'environment';
    }

    private function value(string $key, mixed $fallback): mixed
    {
        $setting = PlatformSetting::where('key', $key)->where('group', self::GROUP)->first();

        return $setting ? $setting->typedValue() : $fallback;
    }

    private function secret(string $key, mixed $fallback): ?string
    {
        $setting = PlatformSetting::where('key', $key)->where('group', self::GROUP)->first();

        if (! $setting) {
            return $this->nullableString($fallback);
        }

        if (! is_string($setting->value) || $setting->value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (DecryptException) {
            return null;
        }
    }

    private function boolean(string $key, bool $fallback): bool
    {
        $value = $this->value($key, $fallback);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
