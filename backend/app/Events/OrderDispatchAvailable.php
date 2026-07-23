<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDispatchAvailable implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $orderId;

    public int $cityId;

    public string $status;

    public ?string $expiresAt;

    public function __construct(Order $order)
    {
        $this->orderId = (int) $order->id;
        $this->cityId = (int) $order->city_id;
        $this->status = $order->status;
        $this->expiresAt = $order->expires_at?->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("drivers.{$this->cityId}"),
            new PrivateChannel('admin.feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.dispatch.available';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'city_id' => $this->cityId,
            'status' => $this->status,
            'expires_at' => $this->expiresAt,
        ];
    }
}
