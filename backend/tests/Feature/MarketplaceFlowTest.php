<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DriverProfile;
use App\Models\FareConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_marketplace_flow_completes_with_transaction_and_wallet_ledger(): void
    {
        $city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        DriverProfile::create([
            'user_id' => $driver->id,
            'vehicle_name' => 'Mercedes Vito',
            'vehicle_plate' => '12345-A-6',
            'vehicle_type' => 'minivan',
            'approval_state' => 'approved',
            'tourism_license_no' => 'TOUR-001',
        ]);

        Wallet::create([
            'user_id' => $driver->id,
            'points_balance' => 250,
            'wallet_balance' => 0,
            'free_rides_remaining' => 1,
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
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($driver);
        $this->postJson('/api/driver/online')->assertOk()->assertJsonPath('data.online_status', true);

        Sanctum::actingAs($rider);
        $orderId = $this->postJson('/api/rider/orders', [
            'city_id' => $city->id,
            'pickup_address' => 'Jemaa el-Fnaa',
            'dropoff_address' => 'Majorelle Garden',
            'distance_km' => 5,
            'eta_min' => 18,
            'pax' => 2,
            'offered_fare' => 140,
            'payment_method' => 'cash',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'searching')
            ->json('data.id');

        Sanctum::actingAs($driver);
        $this->getJson('/api/driver/orders')->assertOk();
        $this->postJson("/api/driver/orders/{$orderId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_driver_id', $driver->id);
        $this->postJson("/api/driver/orders/{$orderId}/arrived")->assertOk()->assertJsonPath('data.status', 'arrived');
        $this->postJson("/api/driver/orders/{$orderId}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/driver/orders/{$orderId}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('transactions', [
            'order_id' => $orderId,
            'type' => 'cash',
            'driver_id' => $driver->id,
            'fare' => 140,
            'fee' => 21,
            'net' => 119,
        ]);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', 'cash_commission')->count());
        $this->assertEquals(229, $driver->wallet()->first()->points_balance);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/transactions')->assertOk();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.orders.completed', 1);
    }
}
