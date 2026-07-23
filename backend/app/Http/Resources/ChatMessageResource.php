<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'sender_id' => $this->sender_id,
            'sender_role' => $this->sender_role,
            'sender' => $this->whenLoaded('sender'),
            'text' => $this->text,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at,
        ];
    }
}
