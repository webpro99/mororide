<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'type' => $this->type,
            'source' => $this->source,
            'driver_id' => $this->driver_id,
            'fare' => (float) $this->fare,
            'fee' => (float) $this->fee,
            'net' => (float) $this->net,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
