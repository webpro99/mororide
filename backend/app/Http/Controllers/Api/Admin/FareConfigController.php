<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateFareConfigRequest;
use App\Models\FareConfig;
use App\Services\AuditLogService;

class FareConfigController extends ApiController
{
    public function show()
    {
        return $this->ok([
            'active' => FareConfig::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('active_from')->orWhere('active_from', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('active_to')->orWhere('active_to', '>=', now());
                })
                ->latest('id')
                ->first(),
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

    public function activate(FareConfig $fareConfig, AuditLogService $auditLogService)
    {
        FareConfig::where('is_active', true)->whereKeyNot($fareConfig->id)->update([
            'is_active' => false,
            'active_to' => now(),
        ]);

        $before = $fareConfig->only(['is_active', 'active_from', 'active_to']);
        $fareConfig->update([
            'is_active' => true,
            'active_from' => now(),
            'active_to' => null,
        ]);

        $auditLogService->record(request()->user(), 'fare_config_activated', $fareConfig, $before, $fareConfig->only(['is_active', 'active_from', 'active_to']));

        return $this->ok($fareConfig->fresh(), 'Fare pricing activated');
    }

    public function deactivate(FareConfig $fareConfig, AuditLogService $auditLogService)
    {
        $before = $fareConfig->only(['is_active', 'active_to']);
        $fareConfig->update([
            'is_active' => false,
            'active_to' => now(),
        ]);

        $auditLogService->record(request()->user(), 'fare_config_deactivated', $fareConfig, $before, $fareConfig->only(['is_active', 'active_to']));

        return $this->ok($fareConfig->fresh(), 'Fare pricing deactivated');
    }
}
