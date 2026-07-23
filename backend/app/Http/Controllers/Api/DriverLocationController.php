<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverLocationUpdated;
use App\Http\Requests\Driver\UpdateLocationRequest;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverLocationController extends ApiController
{
    public function store(UpdateLocationRequest $request)
    {
        $driver = $request->user();

        if (! $this->isEligible($driver->status, $driver->driverProfile)) {
            return response()->json([
                'success' => false,
                'message' => 'Driver must be active, approved, and online to report location.',
                'data' => null,
            ], 422);
        }

        $data = $request->validated();
        $reportedAt = CarbonImmutable::parse($data['reported_at'])->utc();

        [$location, $activeOrder] = DB::transaction(function () use ($driver, $data, $reportedAt) {
            $profile = DriverProfile::query()
                ->where('user_id', $driver->id)
                ->lockForUpdate()
                ->first();

            if (! $this->isEligible($driver->fresh()->status, $profile)) {
                throw ValidationException::withMessages([
                    'driver' => ['Driver must be active, approved, and online to report location.'],
                ]);
            }

            $activeOrder = Order::query()
                ->where('assigned_driver_id', $driver->id)
                ->whereIn('status', [
                    Order::STATUS_ASSIGNED,
                    Order::STATUS_ARRIVED,
                    Order::STATUS_IN_PROGRESS,
                ])
                ->oldest('id')
                ->first();

            if ($activeOrder && (int) $activeOrder->city_id !== (int) $data['city_id']) {
                throw ValidationException::withMessages([
                    'city_id' => ['The reported city must match the active ride city.'],
                ]);
            }

            $latestLocation = DriverLocation::query()
                ->where('driver_id', $driver->id)
                ->latest('reported_at')
                ->latest('id')
                ->first();

            if ($latestLocation && $reportedAt->lessThanOrEqualTo($latestLocation->reported_at)) {
                throw ValidationException::withMessages([
                    'reported_at' => ['The location report must be newer than the previous report.'],
                ]);
            }

            $location = DriverLocation::create([
                'driver_id' => $driver->id,
                'city_id' => $data['city_id'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'reported_at' => $reportedAt,
            ]);

            $profile->update([
                'current_lat' => $data['lat'],
                'current_lng' => $data['lng'],
            ]);

            return [$location, $activeOrder];
        });

        DriverLocationUpdated::dispatch($location, $activeOrder?->id);

        return $this->ok([
            'id' => $location->id,
            'driver_id' => $location->driver_id,
            'city_id' => $location->city_id,
            'lat' => $location->lat,
            'lng' => $location->lng,
            'reported_at' => $location->reported_at->toIso8601String(),
            'active_order_id' => $activeOrder?->id,
        ], 'Driver location updated', 201);
    }

    private function isEligible(string $userStatus, ?DriverProfile $profile): bool
    {
        return $userStatus === 'active'
            && $profile !== null
            && $profile->approval_state === 'approved'
            && $profile->online_status
            && $profile->blocked_at === null;
    }
}
