<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Collection;

class PlatformSettingsService
{
    public function __construct(private AuditLogService $auditLogService) {}

    /**
     * @return Collection<int, PlatformSetting>
     */
    public function all(): Collection
    {
        return PlatformSetting::where('group', '!=', PaymentConfigurationService::GROUP)
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = PlatformSetting::where('key', $key)->first();

        return $setting ? $setting->typedValue() : $default;
    }

    /**
     * Update a batch of settings by key. Unknown keys are ignored so admins
     * cannot inject arbitrary configuration.
     *
     * @param  array<string, mixed>  $values
     * @return Collection<int, PlatformSetting>
     */
    public function update(array $values, User $actor): Collection
    {
        $changed = [];

        foreach ($values as $key => $value) {
            $setting = PlatformSetting::where('key', $key)
                ->where('group', '!=', PaymentConfigurationService::GROUP)
                ->first();

            if (! $setting) {
                continue;
            }

            $old = $setting->value;
            $setting->value = $this->normalize($value, $setting->type);
            $setting->save();

            $changed[$key] = ['from' => $old, 'to' => $setting->value];
        }

        if ($changed !== []) {
            $this->auditLogService->record($actor, 'platform_settings_updated', null, [], $changed);
        }

        return $this->all();
    }

    private function normalize(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}
