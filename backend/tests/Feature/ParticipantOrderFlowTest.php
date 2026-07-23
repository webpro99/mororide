<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->city = City::create(['name' => 'Marrakech', 'is_active' => true]);
    }

    public function test_rider_and_concierge_offer_lists_are_private_and_hide_declines(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $otherRider = User::factory()->create(['role' => 'rider']);
        $concierge = User::factory()->create(['role' => 'concierge']);
        $driver = $this->createDriver();
        $decliningDriver = $this->createDriver();
        $order = $this->createOrder($rider, 'rider');

        $counter = $this->createOffer($order, $driver, [
            'type' => 'counter',
            'amount' => 135,
        ]);
        $this->createOffer($order, $decliningDriver, [
            'type' => 'decline',
            'amount' => null,
            'status' => 'declined',
        ]);

        Sanctum::actingAs($rider);
        $this->getJson("/api/rider/orders/{$order->id}/offers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $counter->id)
            ->assertJsonPath('data.0.driver.id', $driver->id)
            ->assertJsonPath('data.0.driver.vehicle.type', 'sedan');

        Sanctum::actingAs($otherRider);
        $this->getJson("/api/rider/orders/{$order->id}/offers")->assertNotFound();

        Sanctum::actingAs($concierge);
        $this->getJson("/api/concierge/orders/{$order->id}/offers")->assertNotFound();
    }

    public function test_rider_atomically_selects_one_pending_offer_and_cannot_reassign(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $selectedDriver = $this->createDriver();
        $otherDriver = $this->createDriver();
        $thirdDriver = $this->createDriver();
        $decliningDriver = $this->createDriver();
        $order = $this->createOrder($rider, 'rider', ['status' => Order::STATUS_OFFERED]);

        $selected = $this->createOffer($order, $selectedDriver, ['amount' => 137.50]);
        $other = $this->createOffer($order, $otherDriver, ['amount' => 145]);
        $pendingAccept = $this->createOffer($order, $thirdDriver, [
            'type' => 'accept',
            'amount' => 120,
        ]);
        $decline = $this->createOffer($order, $decliningDriver, [
            'type' => 'decline',
            'amount' => null,
            'status' => 'declined',
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/choose-driver", [
            'offer_id' => $selected->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_ASSIGNED)
            ->assertJsonPath('data.assigned_driver_id', $selectedDriver->id)
            ->assertJsonPath('data.final_fare', 137.5);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_ASSIGNED,
            'assigned_driver_id' => $selectedDriver->id,
            'final_fare' => 137.50,
        ]);
        $this->assertSame('accepted', $selected->fresh()->status);
        $this->assertSame('rejected', $other->fresh()->status);
        $this->assertSame('rejected', $pendingAccept->fresh()->status);
        $this->assertSame('declined', $decline->fresh()->status);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $order->id,
            'actor_id' => $rider->id,
            'from_status' => Order::STATUS_OFFERED,
            'to_status' => Order::STATUS_ASSIGNED,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $rider->id,
            'action' => 'requester_selected_driver',
            'target_id' => $order->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $selectedDriver->id,
            'type' => 'offer_accepted',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rider->id,
            'type' => 'driver_selected',
        ]);

        $this->postJson("/api/rider/orders/{$order->id}/choose-driver", [
            'offer_id' => $other->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is no longer available for driver selection.');

        $this->assertSame($selectedDriver->id, $order->fresh()->assigned_driver_id);
        $this->assertSame(1, $order->statusEvents()->where('to_status', Order::STATUS_ASSIGNED)->count());
    }

    public function test_concierge_can_choose_only_a_valid_offer_belonging_to_their_order(): void
    {
        $concierge = User::factory()->create(['role' => 'concierge']);
        $driver = $this->createDriver();
        $otherDriver = $this->createDriver();
        $order = $this->createOrder($concierge, 'concierge', ['status' => Order::STATUS_OFFERED]);
        $otherOrder = $this->createOrder($concierge, 'concierge', ['status' => Order::STATUS_OFFERED]);
        $valid = $this->createOffer($order, $driver, ['amount' => 160]);
        $foreign = $this->createOffer($otherOrder, $otherDriver, ['amount' => 170]);
        $accepted = $this->createOffer($order, $otherDriver, [
            'amount' => 150,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($concierge);
        $this->postJson("/api/concierge/orders/{$order->id}/choose-driver", [
            'offer_id' => $foreign->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A pending accept or counter offer from this order is required.');

        $this->postJson("/api/concierge/orders/{$order->id}/choose-driver", [
            'offer_id' => $accepted->id,
        ])->assertUnprocessable();

        $this->postJson("/api/concierge/orders/{$order->id}/choose-driver", [
            'offer_id' => $valid->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_driver_id', $driver->id)
            ->assertJsonPath('data.final_fare', 160);

        $this->assertSame('accepted', $valid->fresh()->status);
        $this->assertSame('rejected', $accepted->fresh()->status);
    }

    public function test_expired_orders_cannot_select_an_offer(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $order = $this->createOrder($rider, 'rider', [
            'status' => Order::STATUS_OFFERED,
            'expires_at' => now()->subSecond(),
        ]);
        $offer = $this->createOffer($order, $driver, ['amount' => 140]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/choose-driver", [
            'offer_id' => $offer->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order has expired.');

        $this->assertSame(Order::STATUS_OFFERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->assigned_driver_id);
        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_stale_offer_from_an_offline_driver_cannot_be_selected(): void
    {
        $concierge = User::factory()->create(['role' => 'concierge']);
        $driver = $this->createDriver();
        $order = $this->createOrder($concierge, 'concierge', ['status' => Order::STATUS_OFFERED]);
        $offer = $this->createOffer($order, $driver, ['amount' => 175]);
        $driver->driverProfile->update(['online_status' => false]);

        Sanctum::actingAs($concierge);
        $this->postJson("/api/concierge/orders/{$order->id}/choose-driver", [
            'offer_id' => $offer->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selected driver is no longer eligible for assignment.');

        $this->assertSame(Order::STATUS_OFFERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->assigned_driver_id);
        $this->assertSame('pending', $offer->fresh()->status);
        $this->assertDatabaseMissing('order_status_events', [
            'order_id' => $order->id,
            'to_status' => Order::STATUS_ASSIGNED,
        ]);
    }

    public function test_immediate_driver_accept_wins_and_cannot_be_overridden_by_offer_selection(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $acceptingDriver = $this->createDriver();
        $counteringDriver = $this->createDriver();
        $order = $this->createOrder($rider, 'rider');
        $counter = $this->createOffer($order, $counteringDriver, ['amount' => 155]);

        Sanctum::actingAs($acceptingDriver);
        $this->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_ASSIGNED)
            ->assertJsonPath('data.assigned_driver_id', $acceptingDriver->id);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/choose-driver", [
            'offer_id' => $counter->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order is no longer available for driver selection.');

        $order->refresh();
        $this->assertSame($acceptingDriver->id, $order->assigned_driver_id);
        $this->assertEquals(120, $order->final_fare);
        $this->assertSame(1, $order->offers()->where('type', 'accept')->where('status', 'accepted')->count());
    }

    public function test_rider_and_concierge_can_cancel_owned_orders_with_history_and_audit(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $concierge = User::factory()->create(['role' => 'concierge']);
        $driver = $this->createDriver();
        $riderOrder = $this->createOrder($rider, 'rider', ['status' => Order::STATUS_OFFERED]);
        $conciergeOrder = $this->createOrder($concierge, 'concierge', [
            'status' => Order::STATUS_ARRIVED,
            'assigned_driver_id' => $driver->id,
        ]);
        $riderOffer = $this->createOffer($riderOrder, $driver, ['amount' => 135]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$riderOrder->id}/cancel", [
            'reason' => 'Plans changed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        Sanctum::actingAs($concierge);
        $this->postJson("/api/concierge/orders/{$conciergeOrder->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $this->assertSame('rejected', $riderOffer->fresh()->status);
        $this->assertDatabaseHas('orders', [
            'id' => $riderOrder->id,
            'cancelled_by' => $rider->id,
            'cancel_reason' => 'Plans changed',
        ]);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $conciergeOrder->id,
            'actor_id' => $concierge->id,
            'from_status' => Order::STATUS_ARRIVED,
            'to_status' => Order::STATUS_CANCELLED,
        ]);
        $this->assertSame(2, AuditLog::where('action', 'requester_cancelled_order')->count());
    }

    public function test_non_owner_and_in_progress_order_cancellation_are_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $order = $this->createOrder($owner, 'rider', [
            'status' => Order::STATUS_IN_PROGRESS,
            'assigned_driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/rider/orders/{$order->id}/cancel")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/rider/orders/{$order->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Order can no longer be cancelled by its requester.');

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertNull($order->fresh()->cancelled_at);
    }

    public function test_completed_rider_and_concierge_orders_can_each_be_rated_once(): void
    {
        $driver = $this->createDriver();
        $rider = User::factory()->create(['role' => 'rider']);
        $concierge = User::factory()->create(['role' => 'concierge']);
        $riderOrder = $this->createOrder($rider, 'rider', [
            'status' => Order::STATUS_COMPLETED,
            'assigned_driver_id' => $driver->id,
            'completed_at' => now(),
        ]);
        $conciergeOrder = $this->createOrder($concierge, 'concierge', [
            'status' => Order::STATUS_COMPLETED,
            'assigned_driver_id' => $driver->id,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$riderOrder->id}/rating", [
            'score' => 5,
            'comment' => '  Excellent driver  ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.comment', 'Excellent driver')
            ->assertJsonPath('data.driver_id', $driver->id);

        $this->postJson("/api/rider/orders/{$riderOrder->id}/rating", [
            'score' => 4,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This order has already been rated.');

        Sanctum::actingAs($concierge);
        $this->postJson("/api/concierge/orders/{$conciergeOrder->id}/rating", [
            'score' => 4,
        ])->assertCreated();

        $this->assertSame(2, Rating::count());
        $this->assertDatabaseHas('ratings', [
            'order_id' => $riderOrder->id,
            'rater_id' => $rider->id,
            'driver_id' => $driver->id,
            'score' => 5,
            'comment' => 'Excellent driver',
        ]);

        Sanctum::actingAs($rider);
        $this->getJson("/api/rider/orders/{$riderOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.rating.score', 5);
    }

    public function test_rating_requires_ownership_completion_and_valid_payload(): void
    {
        $owner = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $order = $this->createOrder($owner, 'rider', [
            'status' => Order::STATUS_ASSIGNED,
            'assigned_driver_id' => $driver->id,
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/rider/orders/{$order->id}/rating", [
            'score' => 5,
        ])->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/rider/orders/{$order->id}/rating", [
            'score' => 0,
            'comment' => str_repeat('a', 1001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score', 'comment']);

        $this->postJson("/api/rider/orders/{$order->id}/rating", [
            'score' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only completed orders with an assigned driver can be rated.');

        $this->assertSame(0, Rating::count());
    }

    private function createDriver(): User
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'status' => 'active',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'vehicle_name' => 'Dacia Logan',
            'vehicle_plate' => fake()->unique()->bothify('#####-?-#'),
            'vehicle_type' => 'sedan',
            'approval_state' => 'approved',
            'online_status' => true,
        ]);

        return $driver;
    }

    private function createOrder(User $owner, string $source, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'source' => $source,
            'requester_id' => $owner->id,
            'concierge_id' => $source === 'concierge' ? $owner->id : null,
            'city_id' => $this->city->id,
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

    private function createOffer(Order $order, User $driver, array $overrides = []): OrderOffer
    {
        return OrderOffer::create(array_merge([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'type' => 'counter',
            'amount' => 130,
            'message' => 'Counter offer',
            'status' => 'pending',
        ], $overrides));
    }
}
