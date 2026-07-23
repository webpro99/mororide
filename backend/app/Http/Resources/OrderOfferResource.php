<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'type' => $this->type,
            'amount' => $this->amount === null ? null : (float) $this->amount,
            'message' => $this->message,
            'status' => $this->status,
            'driver' => $this->whenLoaded('driver', function () {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                    'vehicle' => $this->driver->driverProfile ? [
                        'name' => $this->driver->driverProfile->vehicle_name,
                        'plate' => $this->driver->driverProfile->vehicle_plate,
                        'type' => $this->driver->driverProfile->vehicle_type,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
