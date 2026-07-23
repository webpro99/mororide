<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\FareConfig;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * Production-safe seed: the reference data the app needs to run (cities, an
 * active fare config, platform settings). It creates NO demo user accounts —
 * the web installer creates the single admin from the operator's own input.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Casablanca', 'Marrakech', 'Rabat', 'Fez',
            'Tangier', 'Agadir', 'Essaouira', 'Chefchaouen',
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(['name' => $city], ['is_active' => true]);
        }

        FareConfig::firstOrCreate(
            ['is_active' => true],
            [
                'base' => 35,
                'per_km' => 8,
                'per_min' => 1.5,
                'per_pax' => 10,
                'floor' => 60,
                'sedan_multiplier' => 1,
                'minivan_multiplier' => 1.25,
                'suv_multiplier' => 1.35,
                'minibus_multiplier' => 1.75,
                'luxury_multiplier' => 2,
                'platform_fee_pct' => 0.15,
                'currency' => 'MAD',
            ]
        );

        $settings = [
            ['key' => 'platform_name', 'value' => 'MoroRide', 'type' => 'string', 'group' => 'general', 'label' => 'Platform name'],
            ['key' => 'support_email', 'value' => 'support@mororide.com', 'type' => 'string', 'group' => 'general', 'label' => 'Support email'],
            ['key' => 'support_phone', 'value' => '+212600000000', 'type' => 'string', 'group' => 'general', 'label' => 'Support phone'],
            ['key' => 'default_currency', 'value' => 'MAD', 'type' => 'string', 'group' => 'billing', 'label' => 'Default currency'],
            ['key' => 'min_points_topup', 'value' => '100', 'type' => 'number', 'group' => 'billing', 'label' => 'Minimum points top-up'],
            ['key' => 'driver_auto_approve', 'value' => '0', 'type' => 'boolean', 'group' => 'verification', 'label' => 'Auto-approve drivers'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general', 'label' => 'Maintenance mode'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
