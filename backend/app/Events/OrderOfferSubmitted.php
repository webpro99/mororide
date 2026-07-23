<?php

namespace App\Events;

use App\Models\OrderOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderOfferSubmitted implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $offerId;

    public int $orderId;

    public int $driverId;

    public string $type;

    public ?float $amount;

    public string $status;

    public function __construct(OrderOffer $offer)
    {
        $this->offerId = (int) $offer->id;
        $this->orderId = (int) $offer->order_id;
        $this->driverId = (int) $offer->driver_id;
        $this->type = $offer->type;
        $this->amount = $offer->amount === null ? null : (float) $offer->amount;
        $this->status = $offer->status;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderId}"),
            new PrivateChannel('admin.feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.offer.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'offer_id' => $this->offerId,
            'order_id' => $this->orderId,
            'driver_id' => $this->driverId,
            'type' => $this->type,
            'amount' => $this->amount,
            'status' => $this->status,
        ];
    }
}
