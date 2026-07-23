<?php

namespace Tests\Feature;

use App\Events\DriverLocationUpdated;
use App\Models\City;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_endpoint_requires_authentication_and_the_driver_role(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $payload = $this->locationPayload($city);

        $this->postJson('/api/driver/location', $payload)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'rider']));
        $this->postJson('/api/driver/location', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is not available for your role.');
    }

    public function test_only_active_approved_online_unblocked_drivers_can_report_location(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $driver = $this->createDriver(['online_status' => false]);
        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/location', $this->locationPayload($city))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Driver must be active, approved, and online to report location.');

        $driver->driverProfile->update(['online_status' => true, 'approval_state' => 'pending']);
        $this->postJson('/api/driver/location', $this->locationPayload($city))->assertUnprocessable();

        $driver->driverProfile->update(['approval_state' => 'approved', 'blocked_at' => now()]);
        $this->postJson('/api/driver/location', $this->locationPayload($city))->assertUnprocessable();

        $driver->driverProfile->update(['blocked_at' => null]);
        $driver->update(['status' => 'suspended']);
        $this->postJson('/api/driver/location', $this->locationPayload($city))->assertUnprocessable();

        $this->assertSame(0, DriverLocation::count());
    }

    public function test_location_validation_rejects_inactive_cities_invalid_coordinates_and_unfresh_timestamps(): void
    {
        $now = CarbonImmutable::parse('2026-07-14 12:00:00');
        $this->travelTo($now);

        $activeCity = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $inactiveCity = City::create(['name' => 'Inactive', 'is_active' => false]);
        $driver = $this->createDriver();
        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/location', $this->locationPayload($inactiveCity))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city_id');

        $this->postJson('/api/driver/location', array_merge($this->locationPayload($activeCity), ['lat' => 91]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lat');

        $this->postJson('/api/driver/location', array_merge($this->locationPayload($activeCity), ['lng' => -181]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lng');

        $this->postJson('/api/driver/location', array_merge($this->locationPayload($activeCity), [
            'reported_at' => $now->subMinutes(6)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('reported_at');

        $this->postJson('/api/driver/location', array_merge($this->locationPayload($activeCity), [
            'reported_at' => $now->addSeconds(61)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('reported_at');

        $this->assertSame(0, DriverLocation::count());
    }

    public function test_location_reports_append_history_update_profile_and_dispatch_a_scoped_event(): void
    {
        Event::fake([DriverLocationUpdated::class]);

        $now = CarbonImmutable::parse('2026-07-14 12:00:00');
        $this->travelTo($now);

        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $driver = $this->createDriver();
        Sanctum::actingAs($driver);

        $first = $this->postJson('/api/driver/location', [
            'city_id' => $city->id,
            'lat' => 31.6295,
            'lng' => -7.9811,
            'reported_at' => $now->subSeconds(10)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.driver_id', $driver->id);

        $second = $this->postJson('/api/driver/location', [
            'city_id' => $city->id,
            'lat' => 31.6301,
            'lng' => -7.9802,
            'reported_at' => $now->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.lat', 31.6301);

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(2, DriverLocation::where('driver_id', $driver->id)->count());
        $this->assertSame(31.6301, $driver->driverProfile->fresh()->current_lat);
        $this->assertSame(-7.9802, $driver->driverProfile->fresh()->current_lng);

        Event::assertDispatchedTimes(DriverLocationUpdated::class, 2);
        Event::assertDispatched(DriverLocationUpdated::class, function (DriverLocationUpdated $event) use ($driver, $city, $now) {
            return $event->driverId === $driver->id
                && $event->cityId === $city->id
                && $event->reportedAt === $now->toIso8601String()
                && collect($event->broadcastOn())->pluck('name')->all() === ["private-driver.{$driver->id}.location"];
        });
    }

    public function test_out_of_order_reports_do_not_overwrite_the_latest_profile_coordinates(): void
    {
        $now = CarbonImmutable::parse('2026-07-14 12:00:00');
        $this->travelTo($now);

        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $driver = $this->createDriver();
        DriverLocation::create([
            'driver_id' => $driver->id,
            'city_id' => $city->id,
            'lat' => 31.6295,
            'lng' => -7.9811,
            'reported_at' => $now->subSeconds(10),
        ]);
        $driver->driverProfile->update(['current_lat' => 31.6295, 'current_lng' => -7.9811]);
        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/location', [
            'city_id' => $city->id,
            'lat' => 30,
            'lng' => -8,
            'reported_at' => $now->subSeconds(20)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('reported_at');

        $this->assertSame(1, DriverLocation::where('driver_id', $driver->id)->count());
        $this->assertSame(31.6295, $driver->driverProfile->fresh()->current_lat);
        $this->assertSame(-7.9811, $driver->driverProfile->fresh()->current_lng);
    }

    public function test_driver_cannot_switch_city_while_assigned_to_an_active_ride(): void
    {
        Event::fake([DriverLocationUpdated::class]);

        $rideCity = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $otherCity = City::create(['name' => 'Casablanca', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = $this->createDriver();
        $order = $this->createOrder($rider, $driver, $rideCity, Order::STATUS_IN_PROGRESS);
        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/location', $this->locationPayload($otherCity))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city_id');

        $this->postJson('/api/driver/location', $this->locationPayload($rideCity))
            ->assertCreated()
            ->assertJsonPath('data.active_order_id', $order->id);

        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'city_id' => $rideCity->id,
        ]);
        $this->assertDatabaseMissing('driver_locations', [
            'driver_id' => $driver->id,
            'city_id' => $otherCity->id,
        ]);
    }

    private function createDriver(array $profileOverrides = []): User
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);

        DriverProfile::create(array_merge([
            'user_id' => $driver->id,
            'approval_state' => 'approved',
            'online_status' => true,
        ], $profileOverrides));

        return $driver;
    }

    private function createOrder(User $rider, User $driver, City $city, string $status): Order
    {
        return Order::create([
            'source' => 'rider',
            'requester_id' => $rider->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup address',
            'dropoff_address' => 'Dropoff address',
            'distance_km' => 5,
            'eta_min' => 15,
            'offered_fare' => 120,
            'payment_method' => 'cash',
            'status' => $status,
            'assigned_driver_id' => $driver->id,
        ]);
    }

    private function locationPayload(City $city): array
    {
        return [
            'city_id' => $city->id,
            'lat' => 31.6295,
            'lng' => -7.9811,
            'reported_at' => now()->toIso8601String(),
        ];
    }
}
