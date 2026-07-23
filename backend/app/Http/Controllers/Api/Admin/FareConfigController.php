<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateFareConfigRequest;
use App\Models\FareConfig;
use App\Services\AuditLogService;
use App\Services\FareService;

class FareConfigController extends ApiController
{
    public function show(FareService $fareService)
    {
        return $this->ok([
            'active' => $fareService->getActiveConfig(),
            'history' => FareConfig::latest()->limit(20)->get(),
        ]);
    }

    public function store(UpdateFareConfigRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        FareConfig::where('is_active', true)->update(['is_active' => false, 'active_to' => now()]);

        $config = FareConfig::create(array_merge($data, [
            'is_active' => true,
            'active_from' => now(),
            'created_by' => $request->user()->id,
            'currency' => $data['currency'] ?? 'MAD',
        ]));

        $auditLogService->record($request->user(), 'fare_config_changed', $config, [], $data);

        return $this->ok($config, 'Fare config saved', 201);
    }
}
