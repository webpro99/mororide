<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\ConciergeProfile;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\FareConfig;
use App\Models\Notification;
use App\Models\PlatformSetting;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $cities = [
            'Casablanca',
            'Marrakech',
            'Rabat',
            'Fez',
            'Tangier',
            'Agadir',
            'Essaouira',
            'Chefchaouen',
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(['name' => $city], ['is_active' => true]);
        }

        $password = Hash::make('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@mororide.test'],
            ['name' => 'MoroRide Admin', 'role' => 'admin', 'status' => 'active', 'password' => $password]
        );

        $rider = User::firstOrCreate(
            ['email' => 'rider@mororide.test'],
            ['name' => 'Sarah Traveler', 'role' => 'rider', 'status' => 'active', 'password' => $password]
        );
        $rider->update(['name' => 'Sarah Traveler']);
        RiderProfile::firstOrCreate(['user_id' => $rider->id], ['preferred_language' => 'en']);

        $driver = User::firstOrCreate(
            ['email' => 'driver@mororide.test'],
            ['name' => 'Karim M.', 'role' => 'driver', 'status' => 'active', 'password' => $password]
        );
        $driver->update(['name' => 'Karim M.']);
        DriverProfile::firstOrCreate(
            ['user_id' => $driver->id],
            [
                'vehicle_name' => 'Mercedes Vito',
                'vehicle_plate' => '12345-A-6',
                'vehicle_type' => 'minivan',
                'tourism_license_no' => 'TOUR-001',
                'approval_state' => 'approved',
            ]
        );

        $moreDrivers = [
            [
                'name' => 'Youssef E.',
                'email' => 'youssef@mororide.test',
                'vehicle_name' => 'Hyundai Tucson',
                'vehicle_plate' => '54321-B-6',
                'vehicle_type' => 'suv',
            ],
            [
                'name' => 'Ahmed B.',
                'email' => 'ahmed@mororide.test',
                'vehicle_name' => 'Dacia Lodgy',
                'vehicle_plate' => '67890-C-6',
                'vehicle_type' => 'minivan',
            ],
        ];

        foreach ($moreDrivers as $driverData) {
            $seedDriver = User::firstOrCreate(
                ['email' => $driverData['email']],
                ['name' => $driverData['name'], 'role' => 'driver', 'status' => 'active', 'password' => $password]
            );

            DriverProfile::firstOrCreate(
                ['user_id' => $seedDriver->id],
                [
                    'vehicle_name' => $driverData['vehicle_name'],
                    'vehicle_plate' => $driverData['vehicle_plate'],
                    'vehicle_type' => $driverData['vehicle_type'],
                    'tourism_license_no' => 'TOUR-'.str_pad((string) $seedDriver->id, 3, '0', STR_PAD_LEFT),
                    'approval_state' => 'approved',
                ]
            );
        }

        // A driver awaiting verification, so the admin verification queue has data.
        $pendingDriver = User::firstOrCreate(
            ['email' => 'pending.driver@mororide.test'],
            ['name' => 'Rachid Pending', 'role' => 'driver', 'status' => 'active', 'password' => $password]
        );
        DriverProfile::firstOrCreate(
            ['user_id' => $pendingDriver->id],
            [
                'vehicle_name' => 'Toyota Prius',
                'vehicle_plate' => '99887-D-1',
                'vehicle_type' => 'sedan',
                'tourism_license_no' => 'TOUR-PENDING',
                'approval_state' => 'pending',
            ]
        );
        foreach (['profile', 'vehicle_out', 'vehicle_in', 'id_front', 'id_back'] as $type) {
            DriverDocument::firstOrCreate(
                ['user_id' => $pendingDriver->id, 'type' => $type],
                [
                    'file_path' => "driver_documents/seed_{$pendingDriver->id}_{$type}.jpg",
                    'original_name' => "{$type}.jpg",
                    'status' => DriverDocument::STATUS_PENDING,
                ]
            );
        }

        $concierge = User::firstOrCreate(
            ['email' => 'concierge@mororide.test'],
            ['name' => 'Riad Concierge', 'role' => 'concierge', 'status' => 'active', 'password' => $password]
        );
        $concierge->update(['name' => 'Riad Concierge']);
        ConciergeProfile::firstOrCreate(
            ['user_id' => $concierge->id],
            ['hotel_name' => 'Riad Dar Zina', 'hotel_address' => 'Marrakech Medina']
        );
        $concierge->conciergeProfile?->update(['hotel_name' => 'Riad Dar Zina']);

        foreach (User::whereIn('role', ['admin', 'rider', 'driver', 'concierge'])->get() as $user) {
            Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'points_balance' => $user->role === 'driver' ? 250 : 0,
                    'wallet_balance' => 0,
                    'free_rides_remaining' => $user->role === 'driver' ? 2 : 0,
                    'currency' => 'MAD',
                ]
            );
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
                'created_by' => $admin->id,
            ]
        );

        $notifications = [
            [
                'type' => 'order_assigned',
                'title' => 'Driver assigned',
                'body' => 'Karim M. is ready to pick up your Marrakech transfer.',
                'data' => ['screen' => 'tracking', 'severity' => 'info'],
            ],
            [
                'type' => 'driver_arrived',
                'title' => 'Driver arriving soon',
                'body' => 'Your driver is close to the pickup point. Keep your phone nearby.',
                'data' => ['screen' => 'tracking', 'severity' => 'info'],
            ],
            [
                'type' => 'payment_ready',
                'title' => 'Cash payment selected',
                'body' => 'Pay the final fare directly to the driver when the ride is completed.',
                'data' => ['screen' => 'payment', 'severity' => 'info'],
            ],
            [
                'type' => 'safety_tip',
                'title' => 'Safety reminder',
                'body' => 'Check the driver name, vehicle, and plate before starting the ride.',
                'data' => ['screen' => 'safety', 'severity' => 'warning'],
            ],
            [
                'type' => 'driver_approved',
                'title' => 'Driver profile approved',
                'body' => 'Your vehicle and tourism license are approved. You can receive ride requests.',
                'role' => 'driver',
                'data' => ['screen' => 'driver', 'severity' => 'success'],
            ],
            [
                'type' => 'points_low',
                'title' => 'Points balance reminder',
                'body' => 'Top up driver points before your next cash commission is due.',
                'role' => 'driver',
                'data' => ['screen' => 'wallet', 'severity' => 'warning'],
            ],
            [
                'type' => 'ride_completed',
                'title' => 'Ride completed',
                'body' => 'A completed ride transaction was recorded in revenue.',
                'role' => 'admin',
                'data' => ['screen' => 'transactions', 'severity' => 'success'],
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::firstOrCreate(
                [
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'role' => $notification['role'] ?? null,
                ],
                [
                    'body' => $notification['body'],
                    'data' => $notification['data'],
                ]
            );
        }

        $settings = [
            ['key' => 'platform_name', 'value' => 'MoroRide', 'type' => 'string', 'group' => 'general', 'label' => 'Platform name'],
            ['key' => 'support_email', 'value' => 'support@mororide.test', 'type' => 'string', 'group' => 'general', 'label' => 'Support email'],
            ['key' => 'support_phone', 'value' => '+212600000000', 'type' => 'string', 'group' => 'general', 'label' => 'Support phone'],
            ['key' => 'default_currency', 'value' => 'MAD', 'type' => 'string', 'group' => 'billing', 'label' => 'Default currency'],
            ['key' => 'min_points_topup', 'value' => '100', 'type' => 'number', 'group' => 'billing', 'label' => 'Minimum points top-up'],
            ['key' => 'billing.free_launch_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'billing', 'label' => 'Free launch mode / billing off'],
            ['key' => 'billing.free_launch_title', 'value' => 'Free during launch — all features unlocked while we build the network.', 'type' => 'string', 'group' => 'billing', 'label' => 'Free launch banner title'],
            ['key' => 'billing.free_launch_body', 'value' => 'No payment needed today. These are the plans that will apply when billing starts.', 'type' => 'string', 'group' => 'billing', 'label' => 'Free launch banner body'],
            ['key' => 'driver_auto_approve', 'value' => '0', 'type' => 'boolean', 'group' => 'verification', 'label' => 'Auto-approve drivers'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general', 'label' => 'Maintenance mode'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
