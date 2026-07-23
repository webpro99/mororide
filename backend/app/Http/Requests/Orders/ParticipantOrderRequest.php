<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

abstract class ParticipantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        if (! $user || ! $order instanceof Order) {
            return false;
        }

        $isOwner = match ($user->role) {
            'rider' => $order->source === 'rider'
                && (int) $order->requester_id === (int) $user->id,
            'concierge' => $order->source === 'concierge'
                && (int) $order->concierge_id === (int) $user->id,
            default => false,
        };

        abort_unless($isOwner, 404);

        return true;
    }
}
