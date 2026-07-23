<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'city' => $this->whenLoaded('city'),
            'requester_id' => $this->requester_id,
            'concierge_id' => $this->concierge_id,
            'assigned_driver_id' => $this->assigned_driver_id,
            'pickup_address' => $this->pickup_address,
            'pickup_lat' => $this->pickup_lat === null ? null : (float) $this->pickup_lat,
            'pickup_lng' => $this->pickup_lng === null ? null : (float) $this->pickup_lng,
            'dropoff_address' => $this->dropoff_address,
            'dropoff_lat' => $this->dropoff_lat === null ? null : (float) $this->dropoff_lat,
            'dropoff_lng' => $this->dropoff_lng === null ? null : (float) $this->dropoff_lng,
            'distance_km' => (float) $this->distance_km,
            'eta_min' => $this->eta_min,
            'pax' => $this->pax,
            'luggage' => $this->luggage,
            'offered_fare' => (float) $this->offered_fare,
            'final_fare' => $this->final_fare === null ? null : (float) $this->final_fare,
            'payment_method' => $this->payment_method,
            'expires_at' => $this->expires_at,
            'assigned_at' => $this->assigned_at,
            'arrived_at' => $this->arrived_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'offers' => $this->whenLoaded('offers'),
            'transaction' => $this->whenLoaded('transaction'),
            'driver' => $this->whenLoaded('driver'),
            'rating' => RatingResource::make($this->whenLoaded('rating')),
        ];
    }
}
