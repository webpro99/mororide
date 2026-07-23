<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\FareConfig;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashRideCompletionWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_insufficient_points_and_free_rides_rolls_back_completion_but_persists_driver_block(): void
    {
        [$driver, $profile, $wallet, $order] = $this->cashRide(points: 0, freeRides: 0);

        Sanctum::actingAs($driver);

        $this->postJson("/api/driver/orders/{$order->id}/complete")
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => WalletService::INSUFFICIENT_CASH_COMMISSION_MESSAGE,
                'data' => null,
            ]);

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
        $this->assertSame('blocked', $driver->fresh()->status);
        $this->assertFalse($profile->fresh()->online_status);
        $this->assertNotNull($profile->fresh()->blocked_at);
        $this->assertSame(0.0, (float) $wallet->fresh()->points_balance);
        $this->assertSame(0, $wallet->fresh()->free_rides_remaining);

        $this->assertSame(0, Transaction::where('order_id', $order->id)->count());
        $this->assertSame(0, WalletLedgerEntry::where('order_id', $order->id)->count());
        $this->assertSame(0, OrderStatusEvent::where('order_id', $order->id)->where('to_status', Order::STATUS_COMPLETED)->count());
        $this->assertSame(0, AuditLog::where('action', 'ride_completed')->where('target_id', $order->id)->count());
    }

    public function test_free_ride_fallback_completes_cash_ride_without_blocking_driver(): void
    {
        [$driver, $profile, $wallet, $order] = $this->cashRide(points: 14.99, freeRides: 1);

        Sanctum::actingAs($driver);

        $this->postJson("/api/driver/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

        $this->assertSame('active', $driver->fresh()->status);
        $this->assertTrue($profile->fresh()->online_status);
        $this->assertNull($profile->fresh()->blocked_at);
        $this->assertSame(14.99, (float) $wallet->fresh()->points_balance);
        $this->assertSame(0, $wallet->fresh()->free_rides_remaining);
        $this->assertSame(1, Transaction::where('order_id', $order->id)->count());

        $entry = WalletLedgerEntry::where('order_id', $order->id)->sole();
        $this->assertSame('free_ride_used', $entry->entry_type);
        $this->assertSame(0.0, (float) $entry->amount);
        $this->assertSame(0.0, (float) $entry->points_delta);
    }

    public function test_exact_points_balance_pays_commission_and_completes_cash_ride(): void
    {
        [$driver, $profile, $wallet, $order] = $this->cashRide(points: 15, freeRides: 0);

        Sanctum::actingAs($driver);

        $this->postJson("/api/driver/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

        $this->assertSame('active', $driver->fresh()->status);
        $this->assertTrue($profile->fresh()->online_status);
        $this->assertSame(0.0, (float) $wallet->fresh()->points_balance);
        $this->assertSame(0, $wallet->fresh()->free_rides_remaining);
        $this->assertSame(1, Transaction::where('order_id', $order->id)->count());

        $entry = WalletLedgerEntry::where('order_id', $order->id)->sole();
        $this->assertSame('cash_commission', $entry->entry_type);
        $this->assertSame(15.0, (float) $entry->amount);
        $this->assertSame(-15.0, (float) $entry->points_delta);
    }

    /**
     * @return array{User, DriverProfile, Wallet, Order}
     */
    private function cashRide(float $points, int $freeRides): array
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);

        $profile = DriverProfile::create([
            'user_id' => $driver->id,
            'approval_state' => 'approved',
            'online_status' => true,
        ]);

        $wallet = Wallet::create([
            'user_id' => $driver->id,
            'points_balance' => $points,
            'wallet_balance' => 0,
            'free_rides_remaining' => $freeRides,
            'currency' => 'MAD',
        ]);

        FareConfig::create([
            'base' => 35,
            'per_km' => 8,
            'per_min' => 1.5,
            'per_pax' => 10,
            'floor' => 60,
            'platform_fee_pct' => 0.15,
            'currency' => 'MAD',
            'is_active' => true,
        ]);

        $order = Order::create([
            'source' => 'rider',
            'requester_id' => $rider->id,
            'city_id' => $city->id,
            'pax' => 1,
            'pickup_address' => 'Jemaa el-Fnaa',
            'dropoff_address' => 'Majorelle Garden',
            'distance_km' => 5,
            'eta_min' => 18,
            'offered_fare' => 100,
            'final_fare' => 100,
            'payment_method' => 'cash',
            'status' => Order::STATUS_IN_PROGRESS,
            'assigned_driver_id' => $driver->id,
            'assigned_at' => now()->subMinutes(20),
            'arrived_at' => now()->subMinutes(15),
            'started_at' => now()->subMinutes(10),
        ]);

        return [$driver, $profile, $wallet, $order];
    }
}
