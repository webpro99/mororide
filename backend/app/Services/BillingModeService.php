<?php

namespace App\Services;

use App\Models\PlatformSetting;

class BillingModeService
{
    public const FREE_LAUNCH_ENABLED = 'billing.free_launch_enabled';
    public const FREE_LAUNCH_TITLE = 'billing.free_launch_title';
    public const FREE_LAUNCH_BODY = 'billing.free_launch_body';

    public function ensureDefaults(): void
    {
        foreach ($this->defaults() as $setting) {
            PlatformSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function freeLaunchEnabled(): bool
    {
        $this->ensureDefaults();

        $setting = PlatformSetting::where('key', self::FREE_LAUNCH_ENABLED)->first();

        return $setting ? (bool) $setting->typedValue() : false;
    }

    public function payload(): array
    {
        $this->ensureDefaults();

        $settings = PlatformSetting::whereIn('key', [
            self::FREE_LAUNCH_ENABLED,
            self::FREE_LAUNCH_TITLE,
            self::FREE_LAUNCH_BODY,
        ])->get()->keyBy('key');

        return [
            'free_launch_enabled' => (bool) ($settings[self::FREE_LAUNCH_ENABLED]?->typedValue() ?? false),
            'billing_state' => ($settings[self::FREE_LAUNCH_ENABLED]?->typedValue() ?? false) ? 'off' : 'paid',
            'title' => (string) ($settings[self::FREE_LAUNCH_TITLE]?->typedValue() ?: 'Free during launch — all features unlocked while we build the network.'),
            'body' => (string) ($settings[self::FREE_LAUNCH_BODY]?->typedValue() ?: 'No payment needed today. These are the plans that will apply when billing starts.'),
        ];
    }

    private function defaults(): array
    {
        return [
            [
                'key' => self::FREE_LAUNCH_ENABLED,
                'value' => '0',
                'type' => 'boolean',
                'group' => 'billing',
                'label' => 'Free launch mode / billing off',
            ],
            [
                'key' => self::FREE_LAUNCH_TITLE,
                'value' => 'Free during launch — all features unlocked while we build the network.',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Free launch banner title',
            ],
            [
                'key' => self::FREE_LAUNCH_BODY,
                'value' => 'No payment needed today. These are the plans that will apply when billing starts.',
                'type' => 'string',
                'group' => 'billing',
                'label' => 'Free launch banner body',
            ],
        ];
    }
}
