<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\OrderAssigned;
use App\Events\OrderStatusChanged;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\ParticipantChatService;
use App\Services\ParticipantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ParticipantRealtimeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_chat_service_dispatches_the_scoped_chat_event(): void
    {
        Event::fake([ChatMessageSent::class]);

        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->order($city, $rider, [
            'status' => Order::STATUS_ASSIGNED,
            'assigned_driver_id' => $driver->id,
            'assigned_at' => now(),
        ]);

        $message = app(ParticipantChatService::class)->send($order, $rider, [
            'text' => 'I am waiting at the entrance.',
        ]);

        Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event) => $event->messageId === $message->id
            && $event->orderId === $order->id
            && collect($event->broadcastOn())->pluck('name')->all() === [
                "private-chat.{$order->id}",
                'private-admin.feed',
            ]
        );
    }

    public function test_participant_selection_and_cancellation_dispatch_realtime_events_after_commit(): void
    {
        Event::fake([OrderAssigned::class, OrderStatusChanged::class]);

        $city = City::create(['name' => 'Rabat', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        DriverProfile::create([
            'user_id' => $driver->id,
            'approval_state' => 'approved',
            'online_status' => true,
        ]);

        $selectableOrder = $this->order($city, $rider);
        $offer = OrderOffer::create([
            'order_id' => $selectableOrder->id,
            'driver_id' => $driver->id,
            'type' => 'counter',
            'amount' => 135,
            'status' => 'pending',
        ]);

        app(ParticipantOrderService::class)->chooseDriver(
            $selectableOrder,
            $rider,
            'rider',
            $offer->id
        );

        Event::assertDispatched(OrderAssigned::class, fn (OrderAssigned $event) => $event->orderId === $selectableOrder->id
            && $event->driverId === $driver->id
        );
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === $selectableOrder->id
            && $event->fromStatus === Order::STATUS_SEARCHING
            && $event->toStatus === Order::STATUS_ASSIGNED
        );

        $cancelledOrder = $this->order($city, $rider, ['status' => Order::STATUS_OFFERED]);
        app(ParticipantOrderService::class)->cancel(
            $cancelledOrder,
            $rider,
            'rider',
            'Plans changed'
        );

        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === $cancelledOrder->id
            && $event->fromStatus === Order::STATUS_OFFERED
            && $event->toStatus === Order::STATUS_CANCELLED
            && collect($event->broadcastOn())->pluck('name')->contains("private-drivers.{$city->id}")
        );
    }

    private function order(City $city, User $requester, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'source' => 'rider',
            'requester_id' => $requester->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Dropoff',
            'distance_km' => 5,
            'eta_min' => 15,
            'offered_fare' => 120,
            'payment_method' => 'cash',
            'status' => Order::STATUS_SEARCHING,
            'expires_at' => now()->addMinutes(10),
        ], $overrides));
    }
}
