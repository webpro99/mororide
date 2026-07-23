<?php

namespace App\Services;

use App\Models\FareConfig;
use RuntimeException;

class FareService
{
    public function getActiveConfig(): FareConfig
    {
        $config = FareConfig::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('active_from')->orWhere('active_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('active_to')->orWhere('active_to', '>=', now());
            })
            ->latest('id')
            ->first();

        if (! $config) {
            throw new RuntimeException('No active fare config found.');
        }

        return $config;
    }

    public function estimateFare(float $distanceKm, int $etaMin, int $pax = 1, string $vehicleType = 'sedan'): array
    {
        $config = $this->getActiveConfig();
        $subtotal = (float) $config->base
            + ($distanceKm * (float) $config->per_km)
            + ($etaMin * (float) $config->per_min)
            + (max(0, $pax - 1) * (float) $config->per_pax);

        $multiplier = $this->vehicleMultiplier($config, $vehicleType);
        $suggestedFare = max((float) $config->floor, round($subtotal * $multiplier, 2));

        return [
            'currency' => $config->currency,
            'distance_km' => round($distanceKm, 2),
            'eta_min' => $etaMin,
            'pax' => $pax,
            'vehicle_type' => $vehicleType,
            'subtotal' => round($subtotal, 2),
            'vehicle_multiplier' => $multiplier,
            'suggested_fare' => $suggestedFare,
            'platform_fee' => $this->calculatePlatformFee($suggestedFare),
            'driver_net' => $this->calculateDriverNet($suggestedFare),
        ];
    }

    public function calculatePlatformFee(float $finalFare): float
    {
        $config = $this->getActiveConfig();

        return round($finalFare * (float) $config->platform_fee_pct, 2);
    }

    public function calculateDriverNet(float $finalFare): float
    {
        return round($finalFare - $this->calculatePlatformFee($finalFare), 2);
    }

    private function vehicleMultiplier(FareConfig $config, string $vehicleType): float
    {
        $field = $vehicleType.'_multiplier';

        return (float) ($config->{$field} ?? $config->sedan_multiplier);
    }
}
