<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\Order;

class ChatController extends ApiController
{
    /**
     * List orders that have chat activity, with message counts (chat archive index).
     */
    public function index()
    {
        $threads = Order::query()
            ->select('orders.*')
            ->selectSub(
                ChatMessage::selectRaw('count(*)')->whereColumn('chat_messages.order_id', 'orders.id'),
                'messages_count'
            )
            ->whereHas('messages')
            ->with(['city', 'requester', 'driver'])
            ->latest()
            ->paginate(30);

        return $this->ok($threads);
    }

    /**
     * Full archived conversation for a single order.
     */
    public function show(Order $order)
    {
        $messages = $order->messages()->with('sender')->orderBy('created_at')->get();

        return $this->ok([
            'order_id' => $order->id,
            'messages' => ChatMessageResource::collection($messages),
        ]);
    }
}
