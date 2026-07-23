<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'points_balance' => (float) $this->points_balance,
            'wallet_balance' => (float) $this->wallet_balance,
            'free_rides_remaining' => $this->free_rides_remaining,
            'currency' => $this->currency,
            'ledger_entries' => $this->whenLoaded('ledgerEntries'),
        ];
    }
}
