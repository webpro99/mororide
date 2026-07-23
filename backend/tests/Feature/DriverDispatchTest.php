<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\DriverDispatchService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DriverDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_queue_requires_an_active_approved_online_driver(): void
    {
        $driver = $this->createDriver(profileOverrides: ['online_status' => false]);
        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/orders')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Driver must be approved and online.');

        $driver->driverProfile->update(['online_status' => true, 'approval_state' => 'pending']);
        $this->getJson('/api/driver/orders')->assertUnprocessable();

        $driver->driverProfile->update(['approval_state' => 'approved']);
        $driver->update(['status' => 'suspended']);
        $this->getJson('/api/driver/orders')->assertUnprocessable();

        $driver->update(['status' => 'active']);
        $this->getJson('/api/driver/orders')->assertOk();
    }

    public function test_order_queue_uses_latest_driver_city_and_excludes_expired_declined_and_closed_orders(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();

        $this->reportLocation($driver, $casablanca, now()->subMinutes(5));
        $this->reportLocation($driver, $marrakech, now());

        $visible = $this->createOrder($rider, $marrakech);
        $expired = $this->createOrder($rider, $marrakech, ['expires_at' => now()->subSecond()]);
        $otherCity = $this->createOrder($rider, $casablanca);
        $declined = $this->createOrder($rider, $marrakech);
        $closed = $this->createOrder($rider, $marrakech, ['status' => Order::STATUS_ASSIGNED]);

        OrderOffer::create([
            'order_id' => $declined->id,
            'driver_id' => $driver->id,
            'type' => 'decline',
            'status' => 'declined',
        ]);

        Sanctum::actingAs($driver);
        $response = $this->getJson('/api/driver/orders')->assertOk();
        $orderIds = collect($response->json('data'))->pluck('id');

        $this->assertSame([$visible->id], $orderIds->all());
        $this->assertFalse($orderIds->contains($expired->id));
        $this->assertFalse($orderIds->contains($otherCity->id));
        $this->assertFalse($orderIds->contains($declined->id));
        $this->assertFalse($orderIds->contains($closed->id));
    }

    public function test_going_online_with_a_city_records_location_and_drives_queue_filtering(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver(profileOverrides: ['online_status' => false]);
        $marrakechOrder = $this->createOrder($rider, $marrakech);
        $this->createOrder($rider, $casablanca);

        Sanctum::actingAs($driver);
        $this->postJson('/api/driver/online', [
            'city_id' => $marrakech->id,
            'current_lat' => 31.6295,
            'current_lng' => -7.9811,
        ])->assertOk()->assertJsonPath('data.online_status', true);

        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'city_id' => $marrakech->id,
        ]);

        $orderIds = collect($this->getJson('/api/driver/orders')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$marrakechOrder->id], $orderIds->all());
    }

    public function test_nearby_online_drivers_are_constrained_by_their_latest_city(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);

        $marrakechDriver = $this->createDriver();
        $movedDriver = $this->createDriver();
        $offlineDriver = $this->createDriver(profileOverrides: ['online_status' => false]);
        $pendingDriver = $this->createDriver(profileOverrides: ['approval_state' => 'pending']);

        $this->reportLocation($marrakechDriver, $marrakech, now());
        $this->reportLocation($movedDriver, $marrakech, now()->subMinute());
        $this->reportLocation($movedDriver, $casablanca, now());
        $this->reportLocation($offlineDriver, $marrakech, now());
        $this->reportLocation($pendingDriver, $marrakech, now());

        $dispatch = app(DriverDispatchService::class);

        $this->assertSame(
            [$marrakechDriver->id],
            $dispatch->getNearbyOnlineDrivers($marrakech->id)->pluck('id')->all()
        );
        $this->assertSame(
            [$movedDriver->id],
            $dispatch->getNearbyOnlineDrivers($casablanca->id)->pluck('id')->all()
        );
    }

    public function test_driver_order_visibility_hides_unavailable_orders_but_keeps_own_assignment_visible(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $assignedDriver = $this->createDriver(profileOverrides: ['online_status' => false]);
        $otherDriver = $this->createDriver();
        $openOrder = $this->createOrder($rider, $marrakech);
        $assignedOrder = $this->createOrder($rider, $marrakech, [
            'status' => Order::STATUS_ASSIGNED,
            'assigned_driver_id' => $assignedDriver->id,
        ]);

        Sanctum::actingAs($assignedDriver);
        $this->getJson("/api/driver/orders/{$openOrder->id}")->assertNotFound();
        $this->getJson("/api/driver/orders/{$assignedOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignedOrder->id);

        Sanctum::actingAs($otherDriver);
        $this->getJson("/api/driver/orders/{$assignedOrder->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Order not found.');
    }

    public function test_accept_rejects_expired_and_cross_city_orders_without_orphan_offers(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $this->reportLocation($driver, $marrakech, now());

        $crossCity = $this->createOrder($rider, $casablanca);
        $expired = $this->createOrder($rider, $marrakech, ['expires_at' => now()->subSecond()]);

        Sanctum::actingAs($driver);
        $this->postJson("/api/driver/orders/{$crossCity->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is outside the driver city.');
        $this->postJson("/api/driver/orders/{$expired->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order has expired.');

        $this->assertSame(0, OrderOffer::count());
        $this->assertNull($crossCity->fresh()->assigned_driver_id);
        $this->assertNull($expired->fresh()->assigned_driver_id);
    }

    public function test_counter_cannot_resurrect_closed_expired_or_cross_city_orders(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $this->reportLocation($driver, $marrakech, now());

        $closed = $this->createOrder($rider, $marrakech, ['status' => Order::STATUS_ASSIGNED]);
        $expired = $this->createOrder($rider, $marrakech, ['expires_at' => now()->subSecond()]);
        $crossCity = $this->createOrder($rider, $casablanca);

        Sanctum::actingAs($driver);
        $this->postJson("/api/driver/orders/{$closed->id}/counter", ['amount' => 130])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is not available for assignment.');
        $this->postJson("/api/driver/orders/{$expired->id}/counter", ['amount' => 130])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order has expired.');
        $this->postJson("/api/driver/orders/{$crossCity->id}/counter", ['amount' => 130])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is outside the driver city.');

        $this->assertSame(Order::STATUS_ASSIGNED, $closed->fresh()->status);
        $this->assertSame(Order::STATUS_SEARCHING, $expired->fresh()->status);
        $this->assertSame(Order::STATUS_SEARCHING, $crossCity->fresh()->status);
        $this->assertSame(0, OrderOffer::where('type', 'counter')->count());
    }

    public function test_decline_requires_an_eligible_driver_and_blocks_later_direct_accept(): void
    {
        $marrakech = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $casablanca = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver(profileOverrides: ['online_status' => false]);
        $this->reportLocation($driver, $marrakech, now());

        $available = $this->createOrder($rider, $marrakech);
        $crossCity = $this->createOrder($rider, $casablanca);
        $closed = $this->createOrder($rider, $marrakech, ['status' => Order::STATUS_ASSIGNED]);

        Sanctum::actingAs($driver);
        $this->postJson("/api/driver/orders/{$available->id}/decline")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Driver must be approved and online.');

        $driver->driverProfile->update(['online_status' => true]);
        $this->postJson("/api/driver/orders/{$crossCity->id}/decline")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is outside the driver city.');
        $this->postJson("/api/driver/orders/{$closed->id}/decline")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is not available for assignment.');

        $this->postJson("/api/driver/orders/{$available->id}/decline")->assertOk();
        $this->postJson("/api/driver/orders/{$available->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Driver already declined this order.');

        $this->assertDatabaseHas('order_offers', [
            'order_id' => $available->id,
            'driver_id' => $driver->id,
            'type' => 'decline',
        ]);
        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $available->id,
            'driver_id' => $driver->id,
            'type' => 'accept',
        ]);
        $this->assertNull($available->fresh()->assigned_driver_id);
    }

    public function test_accept_assigns_once_and_a_second_attempt_cannot_create_an_orphan_offer(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $firstDriver = $this->createDriver();
        $secondDriver = $this->createDriver();
        $this->reportLocation($firstDriver, $city, now());
        $this->reportLocation($secondDriver, $city, now());
        $order = $this->createOrder($rider, $city);

        Sanctum::actingAs($firstDriver);
        $this->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.assigned_driver_id', $firstDriver->id);

        Sanctum::actingAs($secondDriver);
        $this->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is not available for assignment.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_ASSIGNED,
            'assigned_driver_id' => $firstDriver->id,
        ]);
        $this->assertSame(1, OrderOffer::where('order_id', $order->id)->where('type', 'accept')->count());
        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $order->id,
            'driver_id' => $secondDriver->id,
        ]);
    }

    public function test_accept_rolls_back_its_offer_if_assignment_fails(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $this->reportLocation($driver, $city, now());
        $order = $this->createOrder($rider, $city);

        $this->mock(OrderService::class, function (MockInterface $mock) {
            $mock->shouldReceive('assignDriver')
                ->once()
                ->andThrow(new RuntimeException('Assignment failed.'));
        });

        try {
            app(DriverDispatchService::class)->acceptOrder($order, $driver);
            $this->fail('The assignment exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Assignment failed.', $exception->getMessage());
        }

        $this->assertSame(Order::STATUS_SEARCHING, $order->fresh()->status);
        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'type' => 'accept',
        ]);
    }

    private function createDriver(array $userOverrides = [], array $profileOverrides = []): User
    {
        $driver = User::factory()->create(array_merge([
            'role' => 'driver',
            'status' => 'active',
        ], $userOverrides));

        DriverProfile::create(array_merge([
            'user_id' => $driver->id,
            'vehicle_name' => 'Mercedes Vito',
            'vehicle_plate' => fake()->unique()->bothify('#####-?-#'),
            'vehicle_type' => 'minivan',
            'approval_state' => 'approved',
            'online_status' => true,
        ], $profileOverrides));

        return $driver;
    }

    private function createOrder(User $rider, City $city, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'source' => 'rider',
            'requester_id' => $rider->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup address',
            'dropoff_address' => 'Dropoff address',
            'distance_km' => 5,
            'eta_min' => 15,
            'offered_fare' => 120,
            'payment_method' => 'cash',
            'status' => Order::STATUS_SEARCHING,
            'expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    private function reportLocation(User $driver, City $city, $reportedAt): void
    {
        DB::table('driver_locations')->insert([
            'driver_id' => $driver->id,
            'city_id' => $city->id,
            'lat' => 31.6295,
            'lng' => -7.9811,
            'reported_at' => $reportedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
