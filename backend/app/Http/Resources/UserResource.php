<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'suspended_at' => $this->suspended_at,
            'created_at' => $this->created_at,
            'driver_profile' => $this->whenLoaded('driverProfile'),
            'concierge_profile' => $this->whenLoaded('conciergeProfile'),
            'rider_profile' => $this->whenLoaded('riderProfile'),
            'wallet' => $this->whenLoaded('wallet'),
        ];
    }
}
