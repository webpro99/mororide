<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Fare\EstimateFareRequest;
use App\Models\FareConfig;
use App\Services\FareService;
use Illuminate\Http\Request;

class FareController extends ApiController
{
    public function estimate(EstimateFareRequest $request, FareService $fareService)
    {
        $data = $request->validated();

        return $this->ok($fareService->estimateFare(
            (float) $data['distance_km'],
            (int) $data['eta_min'],
            (int) ($data['pax'] ?? 1),
            $data['vehicle_type'] ?? 'sedan'
        ));
    }

    public function show(FareService $fareService)
    {
        return $this->ok($fareService->getActiveConfig());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'base' => ['required', 'numeric', 'min:0'],
            'per_km' => ['required', 'numeric', 'min:0'],
            'per_min' => ['required', 'numeric', 'min:0'],
            'per_pax' => ['required', 'numeric', 'min:0'],
            'floor' => ['required', 'numeric', 'min:0'],
            'sedan_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'minivan_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'suv_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'minibus_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'luxury_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'platform_fee_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        FareConfig::where('is_active', true)->update(['is_active' => false, 'active_to' => now()]);
        $config = FareConfig::create(array_merge($data, [
            'is_active' => true,
            'active_from' => now(),
            'created_by' => $request->user()->id,
            'currency' => $data['currency'] ?? 'MAD',
        ]));

        return $this->ok($config, 'Fare config saved', 201);
    }
}
