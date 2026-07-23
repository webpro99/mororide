<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'driver' => $this->whenLoaded('driver'),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'method' => $this->method,
            'reference' => $this->reference,
            'note' => $this->note,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
        ];
    }
}
