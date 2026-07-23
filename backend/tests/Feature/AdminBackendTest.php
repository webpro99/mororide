<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\City;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBackendTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeOrder(User $rider, City $city, string $status = Order::STATUS_SEARCHING): Order
    {
        return Order::create([
            'source' => 'rider',
            'requester_id' => $rider->id,
            'city_id' => $city->id,
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Dropoff',
            'distance_km' => 5,
            'eta_min' => 12,
            'offered_fare' => 100,
            'status' => $status,
        ]);
    }

    public function test_dashboard_returns_overview(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['users', 'drivers', 'orders', 'revenue', 'documents_pending', 'payouts_pending', 'recent_activity'],
            ]);
    }

    public function test_admin_can_list_filter_and_suspend_users(): void
    {
        $admin = $this->admin();
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?role=rider')->assertOk();
        $this->getJson("/api/admin/users/{$rider->id}")->assertOk()->assertJsonPath('data.id', $rider->id);

        $this->patchJson("/api/admin/users/{$rider->id}/status", ['status' => 'suspended', 'reason' => 'policy violation'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('users', ['id' => $rider->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_status_changed']);
    }

    public function test_admin_reactivation_clears_driver_block_but_keeps_driver_offline(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'blocked']);
        DriverProfile::create([
            'user_id' => $driver->id,
            'approval_state' => 'approved',
            'online_status' => false,
            'blocked_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$driver->id}/status", [
            'status' => 'active',
            'reason' => 'Wallet issue resolved',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.driver_profile.online_status', false);

        $this->assertNull($driver->fresh()->driverProfile->blocked_at);
        $this->assertFalse($driver->fresh()->driverProfile->online_status);

        Sanctum::actingAs($driver->fresh());
        $this->postJson('/api/driver/online')
            ->assertOk()
            ->assertJsonPath('data.online_status', true);
    }

    public function test_admin_can_flag_and_cancel_orders(): void
    {
        $admin = $this->admin();
        $city = City::create(['name' => 'Fez', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $order = $this->makeOrder($rider, $city);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/orders/{$order->id}/flag", ['reason' => 'suspicious pricing'])->assertOk();
        $this->assertNotNull($order->fresh()->flagged_at);

        $this->postJson("/api/admin/orders/{$order->id}/cancel", ['reason' => 'duplicate'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('audit_logs', ['action' => 'ride_cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order_flagged']);
    }

    public function test_admin_can_review_documents_and_approve_driver(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'driver']);
        DriverProfile::create(['user_id' => $driver->id, 'vehicle_type' => 'sedan', 'approval_state' => 'pending']);
        foreach (['profile', 'vehicle_out'] as $type) {
            DriverDocument::create(['user_id' => $driver->id, 'type' => $type, 'file_path' => "x/{$type}.jpg", 'status' => 'pending']);
        }
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/drivers?approval_state=pending')->assertOk();

        $this->getJson("/api/admin/drivers/{$driver->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.has_all_required', false);

        $this->postJson("/api/admin/drivers/{$driver->id}/request-document", ['type' => 'license', 'note' => 'needed'])
            ->assertCreated();
        $this->assertDatabaseHas('driver_document_requests', ['user_id' => $driver->id, 'type' => 'license']);

        $this->postJson("/api/admin/drivers/{$driver->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.driver_profile.approval_state', 'approved');

        $this->assertDatabaseHas('driver_profiles', ['user_id' => $driver->id, 'approval_state' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'driver_approved']);
        $this->assertDatabaseHas('notifications', ['user_id' => $driver->id, 'type' => 'driver_approved']);
    }

    public function test_admin_can_reject_driver(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'driver']);
        DriverProfile::create(['user_id' => $driver->id, 'vehicle_type' => 'sedan', 'approval_state' => 'pending', 'online_status' => true]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/drivers/{$driver->id}/reject", ['reason' => 'blurry documents'])
            ->assertOk()
            ->assertJsonPath('data.driver_profile.approval_state', 'rejected');

        $this->assertDatabaseHas('driver_profiles', ['user_id' => $driver->id, 'approval_state' => 'rejected', 'online_status' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'driver_rejected']);
    }

    public function test_admin_can_adjust_wallet_and_writes_ledger_entry(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'driver']);
        $wallet = Wallet::create(['user_id' => $driver->id, 'points_balance' => 100, 'wallet_balance' => 0, 'free_rides_remaining' => 0, 'currency' => 'MAD']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/wallets/{$wallet->id}/adjust", ['points_delta' => 50, 'reason' => 'goodwill bonus'])
            ->assertOk();

        $this->assertEquals(150, $wallet->fresh()->points_balance);
        $this->assertDatabaseHas('wallet_ledger_entries', ['entry_type' => 'manual_adjustment', 'user_id' => $driver->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet_adjusted']);
    }

    public function test_admin_can_create_and_confirm_payout(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create(['role' => 'driver']);
        Wallet::create(['user_id' => $driver->id, 'points_balance' => 0, 'wallet_balance' => 300, 'free_rides_remaining' => 0, 'currency' => 'MAD']);
        Sanctum::actingAs($admin);

        $payoutId = $this->postJson('/api/admin/payouts', ['driver_id' => $driver->id, 'amount' => 200, 'note' => 'weekly payout'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->postJson("/api/admin/payouts/{$payoutId}/confirm", ['reference' => 'BANK-REF-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(100, $driver->wallet()->first()->wallet_balance);
        $this->assertDatabaseHas('wallet_ledger_entries', ['entry_type' => 'payout', 'user_id' => $driver->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout_confirmed']);
    }

    public function test_admin_can_read_chat_archive(): void
    {
        $admin = $this->admin();
        $city = City::create(['name' => 'Rabat', 'is_active' => true]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeOrder($rider, $city);
        ChatMessage::create(['order_id' => $order->id, 'sender_id' => $rider->id, 'sender_role' => 'rider', 'text' => 'Hello']);
        ChatMessage::create(['order_id' => $order->id, 'sender_id' => $driver->id, 'sender_role' => 'driver', 'text' => 'On my way']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/chats')->assertOk();
        $this->getJson("/api/admin/chats/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_admin_can_read_and_update_settings(): void
    {
        $admin = $this->admin();
        PlatformSetting::create(['key' => 'support_email', 'value' => 'old@mororide.test', 'type' => 'string', 'group' => 'general']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/settings')->assertOk();

        $this->postJson('/api/admin/settings', ['settings' => ['support_email' => 'new@mororide.test']])->assertOk();

        $this->assertDatabaseHas('platform_settings', ['key' => 'support_email', 'value' => 'new@mororide.test']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform_settings_updated']);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $admin = $this->admin();
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$rider->id}/status", ['status' => 'blocked', 'reason' => 'fraud'])->assertOk();

        $this->getJson('/api/admin/audit-logs?action=user_status_changed')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_non_admin_cannot_access_admin_backend(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'rider']));

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }
}
