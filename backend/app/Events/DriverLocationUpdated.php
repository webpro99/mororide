<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $locationId;

    public int $driverId;

    public int $cityId;

    public float $lat;

    public float $lng;

    public string $reportedAt;

    public function __construct(DriverLocation $location, public ?int $activeOrderId = null)
    {
        $this->locationId = (int) $location->id;
        $this->driverId = (int) $location->driver_id;
        $this->cityId = (int) $location->city_id;
        $this->lat = (float) $location->lat;
        $this->lng = (float) $location->lng;
        $this->reportedAt = $location->reported_at->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("driver.{$this->driverId}.location")];
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'location_id' => $this->locationId,
            'driver_id' => $this->driverId,
            'city_id' => $this->cityId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'reported_at' => $this->reportedAt,
            'active_order_id' => $this->activeOrderId,
        ];
    }
}
