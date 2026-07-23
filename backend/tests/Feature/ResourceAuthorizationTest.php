<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(
        User $requester,
        City $city,
        string $source,
        ?User $concierge = null
    ): Order {
        return Order::create([
            'source' => $source,
            'requester_id' => $requester->id,
            'concierge_id' => $concierge?->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Dropoff',
            'distance_km' => 5,
            'eta_min' => 12,
            'offered_fare' => 100,
            'status' => Order::STATUS_SEARCHING,
        ]);
    }

    private function makeNotification(?User $user = null, ?string $role = null): Notification
    {
        return Notification::create([
            'user_id' => $user?->id,
            'role' => $role,
            'type' => 'test',
            'title' => 'Test notification',
            'body' => 'Test body',
        ]);
    }

    public function test_rider_can_show_only_their_own_rider_order(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'rider']);
        $otherRider = User::factory()->create(['role' => 'rider']);
        $order = $this->makeOrder($owner, $city, 'rider');

        Sanctum::actingAs($owner);
        $this->getJson("/api/rider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        Sanctum::actingAs($otherRider);
        $this->getJson("/api/rider/orders/{$order->id}")->assertNotFound();
    }

    public function test_concierge_can_show_only_their_own_concierge_order(): void
    {
        $city = City::create(['name' => 'Rabat', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'concierge']);
        $otherConcierge = User::factory()->create(['role' => 'concierge']);
        $order = $this->makeOrder($owner, $city, 'concierge', $owner);

        Sanctum::actingAs($owner);
        $this->getJson("/api/concierge/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        Sanctum::actingAs($otherConcierge);
        $this->getJson("/api/concierge/orders/{$order->id}")->assertNotFound();
    }

    public function test_user_can_mark_only_their_own_direct_notification_as_read(): void
    {
        $owner = User::factory()->create(['role' => 'rider']);
        $otherRider = User::factory()->create(['role' => 'rider']);
        $ownedNotification = $this->makeNotification($owner);
        $forbiddenNotification = $this->makeNotification($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/notifications/{$ownedNotification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $ownedNotification->id);
        $this->assertNotNull($ownedNotification->fresh()->read_at);

        Sanctum::actingAs($otherRider);
        $this->postJson("/api/notifications/{$forbiddenNotification->id}/read")
            ->assertNotFound();
        $this->assertNull($forbiddenNotification->fresh()->read_at);
    }

    public function test_role_and_global_notifications_use_per_user_read_receipts(): void
    {
        $firstRider = User::factory()->create(['role' => 'rider']);
        $secondRider = User::factory()->create(['role' => 'rider']);
        $roleNotification = $this->makeNotification(role: 'rider');
        $globalNotification = $this->makeNotification();

        Sanctum::actingAs($firstRider);
        $this->postJson("/api/notifications/{$roleNotification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $roleNotification->id);
        $this->postJson("/api/notifications/{$globalNotification->id}/read")->assertOk();

        $firstRiderNotifications = collect(
            $this->getJson('/api/notifications')->assertOk()->json('data')
        );
        $this->assertNotNull(
            $firstRiderNotifications->firstWhere('id', $roleNotification->id)['read_at']
        );
        $this->assertNotNull(
            $firstRiderNotifications->firstWhere('id', $globalNotification->id)['read_at']
        );

        $this->assertNull($roleNotification->fresh()->read_at);
        $this->assertNull($globalNotification->fresh()->read_at);
        $this->assertDatabaseHas('notification_reads', [
            'notification_id' => $roleNotification->id,
            'user_id' => $firstRider->id,
        ]);
        $this->assertDatabaseHas('notification_reads', [
            'notification_id' => $globalNotification->id,
            'user_id' => $firstRider->id,
        ]);

        Sanctum::actingAs($secondRider);
        $secondRiderNotifications = collect(
            $this->getJson('/api/notifications')->assertOk()->json('data')
        );
        $this->assertNull(
            $secondRiderNotifications->firstWhere('id', $roleNotification->id)['read_at']
        );
        $this->assertNull(
            $secondRiderNotifications->firstWhere('id', $globalNotification->id)['read_at']
        );
    }

    public function test_user_cannot_mark_another_roles_notification_as_read(): void
    {
        $driverNotification = $this->makeNotification(role: 'driver');
        $rider = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($rider);
        $this->postJson("/api/notifications/{$driverNotification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseMissing('notification_reads', [
            'notification_id' => $driverNotification->id,
            'user_id' => $rider->id,
        ]);
    }

    public function test_notification_listing_uses_an_optional_sanctum_bearer_token(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $direct = $this->makeNotification($rider);
        $forRiders = $this->makeNotification(role: 'rider');
        $forDrivers = $this->makeNotification(role: 'driver');
        $global = $this->makeNotification();

        $token = $rider->createToken('notification-test')->plainTextToken;
        $notifications = collect(
            $this->withToken($token)->getJson('/api/notifications')
                ->assertOk()
                ->json('data')
        );

        $visibleIds = $notifications->pluck('id');
        $this->assertTrue($visibleIds->contains($direct->id));
        $this->assertTrue($visibleIds->contains($forRiders->id));
        $this->assertTrue($visibleIds->contains($global->id));
        $this->assertFalse($visibleIds->contains($forDrivers->id));
    }

    public function test_marking_notification_read_requires_authentication(): void
    {
        $notification = $this->makeNotification();

        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertUnauthorized();

        $this->assertDatabaseCount('notification_reads', 0);
    }
}
