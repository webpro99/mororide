<?php

namespace App\Http\Controllers\Api;

use App\Models\City;
use App\Models\FareConfig;
use App\Models\User;

class CatalogController extends ApiController
{
    public function index()
    {
        $fareConfig = FareConfig::where('is_active', true)->latest()->first();

        $vehicles = collect([
            ['key' => 'sedan', 'name' => 'Sedan', 'icon' => 'car-side', 'multiplier_column' => 'sedan_multiplier'],
            ['key' => 'minivan', 'name' => 'Minivan', 'icon' => 'shuttle-van', 'multiplier_column' => 'minivan_multiplier'],
            ['key' => 'suv', 'name' => 'SUV', 'icon' => 'car', 'multiplier_column' => 'suv_multiplier'],
            ['key' => 'minibus', 'name' => 'Minibus', 'icon' => 'bus', 'multiplier_column' => 'minibus_multiplier'],
            ['key' => 'luxury', 'name' => 'Luxury', 'icon' => 'gem', 'multiplier_column' => 'luxury_multiplier'],
        ])->map(function (array $vehicle) use ($fareConfig) {
            return [
                'key' => $vehicle['key'],
                'name' => $vehicle['name'],
                'icon' => $vehicle['icon'],
                'multiplier' => $fareConfig ? (float) $fareConfig->{$vehicle['multiplier_column']} : 1.0,
            ];
        })->values();

        $drivers = User::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->whereHas('driverProfile', fn ($query) => $query->where('approval_state', 'approved'))
            ->with('driverProfile')
            ->orderBy('name')
            ->get()
            ->map(function (User $driver) {
                $profile = $driver->driverProfile;

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'initials' => collect(explode(' ', $driver->name))
                        ->filter()
                        ->map(fn ($part) => mb_substr($part, 0, 1))
                        ->take(2)
                        ->implode(''),
                    'vehicle_name' => $profile?->vehicle_name,
                    'vehicle_plate' => $profile?->vehicle_plate,
                    'vehicle_type' => $profile?->vehicle_type,
                    'approval_state' => $profile?->approval_state,
                    'online_status' => (bool) $profile?->online_status,
                ];
            })
            ->values();

        return $this->ok([
            'cities' => City::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'fare_config' => $fareConfig,
        ]);
    }
}
