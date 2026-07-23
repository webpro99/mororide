<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\OrderAssigned;
use App\Events\OrderDispatchAvailable;
use App\Events\OrderOfferSubmitted;
use App\Events\OrderStatusChanged;
use App\Models\ChatMessage;
use App\Models\City;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\FareConfig;
use App\Models\Order;
use App\Models\User;
use App\Services\DriverDispatchService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RealtimeBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_auth_route_is_registered_with_sanctum(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === 'broadcasting/auth' && in_array('POST', $candidate->methods(), true));

        $this->assertNotNull($route);
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('api', $route->gatherMiddleware());

        $this->postJson('/broadcasting/auth')->assertUnauthorized();
    }

    public function test_private_channel_authorization_is_scoped_to_city_participants_and_admins(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $concierge = User::factory()->create(['role' => 'concierge']);
        $outsider = User::factory()->create(['role' => 'rider']);
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = $this->createDriver($marrakech);
        $otherDriver = $this->createDriver($casablanca);
        $order = $this->createOrder($rider, $driver, $marrakech, [
            'status' => Order::STATUS_ASSIGNED,
        ]);
        $conciergeOrder = $this->createOrder($concierge, $otherDriver, $casablanca, [
            'source' => 'concierge',
            'concierge_id' => $concierge->id,
            'status' => Order::STATUS_ASSIGNED,
        ]);
        $unassignedOrder = $this->createOrder($rider, null, $marrakech);

        $channels = Broadcast::connection()->getChannels();

        $this->assertArrayHasKey('drivers.{city_id}', $channels);
        $this->assertArrayHasKey('order.{order_id}', $channels);
        $this->assertArrayHasKey('driver.{driver_id}.location', $channels);
        $this->assertArrayHasKey('chat.{order_id}', $channels);
        $this->assertArrayHasKey('admin.feed', $channels);

        $this->assertTrue($channels['drivers.{city_id}']($driver, $marrakech->id));
        $this->assertFalse($channels['drivers.{city_id}']($driver, $casablanca->id));
        $this->assertFalse($channels['drivers.{city_id}']($otherDriver, $marrakech->id));
        $driver->driverProfile->update(['online_status' => false]);
        $this->assertFalse($channels['drivers.{city_id}']($driver->fresh(), $marrakech->id));

        foreach ([$rider, $driver, $admin] as $participant) {
            $this->assertTrue($channels['order.{order_id}']($participant, $order->id));
            $this->assertTrue($channels['chat.{order_id}']($participant, $order->id));
        }

        $this->assertFalse($channels['order.{order_id}']($concierge, $order->id));
        $this->assertFalse($channels['chat.{order_id}']($concierge, $order->id));
        $this->assertFalse($channels['order.{order_id}']($outsider, $order->id));
        $this->assertFalse($channels['chat.{order_id}']($outsider, $order->id));
        $this->assertTrue($channels['order.{order_id}']($concierge, $conciergeOrder->id));
        $this->assertTrue($channels['chat.{order_id}']($concierge, $conciergeOrder->id));
        $this->assertTrue($channels['order.{order_id}']($rider, $unassignedOrder->id));
        $this->assertFalse($channels['chat.{order_id}']($rider, $unassignedOrder->id));

        $this->assertTrue($channels['driver.{driver_id}.location']($rider, $driver->id));
        $this->assertFalse($channels['driver.{driver_id}.location']($concierge, $driver->id));
        $this->assertTrue($channels['driver.{driver_id}.location']($concierge, $otherDriver->id));
        $this->assertTrue($channels['driver.{driver_id}.location']($driver, $driver->id));
        $this->assertTrue($channels['driver.{driver_id}.location']($admin, $driver->id));
        $this->assertFalse($channels['driver.{driver_id}.location']($outsider, $driver->id));
        $this->assertFalse($channels['driver.{driver_id}.location']($otherDriver, $driver->id));

        $this->assertTrue($channels['admin.feed']($admin));
        $this->assertFalse($channels['admin.feed']($rider));

        $order->update(['status' => Order::STATUS_COMPLETED]);
        $this->assertFalse($channels['driver.{driver_id}.location']($rider, $driver->id));
    }

    public function test_order_services_dispatch_minimal_dispatch_offer_assignment_and_status_events(): void
    {
        Event::fake([
            OrderDispatchAvailable::class,
            OrderOfferSubmitted::class,
            OrderAssigned::class,
            OrderStatusChanged::class,
        ]);

        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver($city);
        $this->createFareConfig($admin);

        $order = app(OrderService::class)->createRiderOrder($rider, [
            'city_id' => $city->id,
            'pickup_address' => 'Jemaa el-Fnaa',
            'dropoff_address' => 'Majorelle Garden',
            'distance_km' => 5,
            'eta_min' => 18,
            'offered_fare' => 140,
            'payment_method' => 'cash',
        ]);

        Event::assertDispatched(OrderDispatchAvailable::class, fn (OrderDispatchAvailable $event) => $event->orderId === $order->id
            && collect($event->broadcastOn())->pluck('name')->contains("private-drivers.{$city->id}"));
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === $order->id
            && $event->fromStatus === null
            && $event->toStatus === Order::STATUS_SEARCHING);

        $offer = app(DriverDispatchService::class)->counterOrder($order, $driver, 150, 'Counter');
        Event::assertDispatched(OrderOfferSubmitted::class, fn (OrderOfferSubmitted $event) => $event->offerId === $offer->id
            && $event->type === 'counter'
            && $event->amount === 150.0
            && collect($event->broadcastOn())->pluck('name')->contains("private-order.{$order->id}"));

        $assignedOrder = app(DriverDispatchService::class)->acceptOrder($order->fresh(), $driver);
        Event::assertDispatched(OrderAssigned::class, fn (OrderAssigned $event) => $event->orderId === $assignedOrder->id
            && $event->driverId === $driver->id
            && collect($event->broadcastOn())->pluck('name')->contains('private-admin.feed'));
        Event::assertDispatched(OrderOfferSubmitted::class, fn (OrderOfferSubmitted $event) => $event->orderId === $order->id
            && $event->type === 'accept');
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === $order->id
            && $event->fromStatus === Order::STATUS_OFFERED
            && $event->toStatus === Order::STATUS_ASSIGNED);

        app(OrderService::class)->markArrived($assignedOrder, $driver);
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === $order->id
            && $event->toStatus === Order::STATUS_ARRIVED);
    }

    public function test_chat_event_uses_only_the_private_order_chat_and_admin_channels(): void
    {
        Event::fake([ChatMessageSent::class]);

        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $order = $this->createOrder($rider, null, $city);
        $message = ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $rider->id,
            'sender_role' => 'rider',
            'text' => 'I am at the pickup point.',
        ]);

        ChatMessageSent::dispatch($message);

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($message, $order) {
            return $event->messageId === $message->id
                && $event->broadcastWith()['text'] === 'I am at the pickup point.'
                && collect($event->broadcastOn())->pluck('name')->all() === [
                    "private-chat.{$order->id}",
                    'private-admin.feed',
                ];
        });
    }

    public function test_open_order_cancellation_event_removes_it_from_the_city_dispatch_channel(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $order = $this->createOrder($rider, null, $city);

        $openCancellation = new OrderStatusChanged(
            $order,
            $rider->id,
            Order::STATUS_SEARCHING,
            Order::STATUS_CANCELLED
        );
        $assignedCancellation = new OrderStatusChanged(
            $order,
            $rider->id,
            Order::STATUS_ASSIGNED,
            Order::STATUS_CANCELLED
        );

        $this->assertSame([
            "private-order.{$order->id}",
            'private-admin.feed',
            "private-drivers.{$city->id}",
        ], collect($openCancellation->broadcastOn())->pluck('name')->all());
        $this->assertSame([
            "private-order.{$order->id}",
            'private-admin.feed',
        ], collect($assignedCancellation->broadcastOn())->pluck('name')->all());
        $this->assertArrayNotHasKey('pickup_address', $openCancellation->broadcastWith());
    }

    private function createDriver(City $city): User
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        DriverProfile::create([
            'user_id' => $driver->id,
            'approval_state' => 'approved',
            'online_status' => true,
        ]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'city_id' => $city->id,
            'lat' => 31.6295,
            'lng' => -7.9811,
            'reported_at' => now(),
        ]);

        return $driver;
    }

    private function createOrder(User $requester, ?User $driver, City $city, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'source' => 'rider',
            'requester_id' => $requester->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup address',
            'dropoff_address' => 'Dropoff address',
            'distance_km' => 5,
            'eta_min' => 15,
            'offered_fare' => 120,
            'payment_method' => 'cash',
            'status' => Order::STATUS_SEARCHING,
            'assigned_driver_id' => $driver?->id,
        ], $overrides));
    }

    private function createFareConfig(User $admin): void
    {
        FareConfig::create([
            'base' => 35,
            'per_km' => 8,
            'per_min' => 1.5,
            'per_pax' => 10,
            'floor' => 60,
            'platform_fee_pct' => 0.15,
            'currency' => 'MAD',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
