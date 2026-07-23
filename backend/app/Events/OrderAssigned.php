<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAssigned implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $orderId;

    public int $cityId;

    public int $driverId;

    public string $status;

    public function __construct(Order $order)
    {
        $this->orderId = (int) $order->id;
        $this->cityId = (int) $order->city_id;
        $this->driverId = (int) $order->assigned_driver_id;
        $this->status = $order->status;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderId}"),
            new PrivateChannel("drivers.{$this->cityId}"),
            new PrivateChannel('admin.feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'city_id' => $this->cityId,
            'assigned_driver_id' => $this->driverId,
            'status' => $this->status,
        ];
    }
}
