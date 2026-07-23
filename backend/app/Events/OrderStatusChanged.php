<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $orderId;

    public int $cityId;

    public ?int $actorId;

    public ?string $fromStatus;

    public string $toStatus;

    public string $changedAt;

    public function __construct(Order $order, ?int $actorId, ?string $fromStatus, string $toStatus)
    {
        $this->orderId = (int) $order->id;
        $this->cityId = (int) $order->city_id;
        $this->actorId = $actorId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->changedAt = ($order->updated_at ?? now())->toIso8601String();
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("order.{$this->orderId}"),
            new PrivateChannel('admin.feed'),
        ];

        if (in_array($this->fromStatus, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)
            && in_array($this->toStatus, [Order::STATUS_CANCELLED, Order::STATUS_EXPIRED], true)) {
            $channels[] = new PrivateChannel("drivers.{$this->cityId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'city_id' => $this->cityId,
            'actor_id' => $this->actorId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'changed_at' => $this->changedAt,
        ];
    }
}
