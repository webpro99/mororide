<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PaymentConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private AdminPaymentSettingsGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'stripe.enabled' => false,
            'stripe.secret_key' => null,
            'stripe.publishable_key' => null,
            'stripe.webhook_secret' => null,
            'stripe.connect.enabled' => false,
        ]);

        $this->gateway = new AdminPaymentSettingsGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_admin_can_open_payment_settings_without_exposing_secrets(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->getJson('/api/admin/payment-settings')
            ->assertOk()
            ->assertJsonPath('data.status.ready', false)
            ->assertJsonPath('data.status.secret_key_configured', false)
            ->assertJsonPath('data.webhook.url', 'http://localhost/api/webhooks/stripe');

        $this->assertArrayNotHasKey('secret_key', $response->json('data.values'));
        $this->assertArrayNotHasKey('webhook_secret', $response->json('data.values'));
        $this->assertSame(14, PlatformSetting::where('group', 'payments')->count());
    }

    public function test_admin_saves_encrypted_masked_settings_and_generic_endpoint_cannot_access_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/payment-settings', $this->validSettings())
            ->assertOk()
            ->assertJsonPath('data.status.ready', true)
            ->assertJsonPath('data.status.secret_key_configured', true)
            ->assertJsonPath('data.status.webhook_secret_configured', true);

        $secretRow = PlatformSetting::where('key', 'payment.stripe_secret_key')->firstOrFail();
        $webhookRow = PlatformSetting::where('key', 'payment.stripe_webhook_secret')->firstOrFail();
        $this->assertNotSame('sk_test_admin_123456789', $secretRow->value);
        $this->assertNotSame('whsec_admin_123456789', $webhookRow->value);
        $this->assertSame('sk_test_admin_123456789', Crypt::decryptString($secretRow->value));
        $this->assertSame('whsec_admin_123456789', Crypt::decryptString($webhookRow->value));
        $this->assertStringNotContainsString('sk_test_admin_123456789', $response->getContent());
        $this->assertStringNotContainsString('whsec_admin_123456789', $response->getContent());

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonMissing(['key' => 'payment.stripe_secret_key']);

        $this->postJson('/api/admin/settings', [
            'settings' => ['payment.stripe_secret_key' => 'attacker-value'],
        ])->assertOk();
        $this->assertSame('sk_test_admin_123456789', Crypt::decryptString($secretRow->fresh()->value));

        $audit = AuditLog::where('action', 'payment_settings_updated')->firstOrFail();
        $encodedAudit = json_encode([$audit->old_values, $audit->new_values], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('sk_test_admin_123456789', $encodedAudit);
        $this->assertStringNotContainsString('whsec_admin_123456789', $encodedAudit);
    }

    public function test_blank_secrets_are_kept_and_can_only_be_cleared_explicitly(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/payment-settings', $this->validSettings())->assertOk();

        $keep = $this->validSettings([
            'enabled' => false,
            'secret_key' => '',
            'webhook_secret' => '',
        ]);
        $this->putJson('/api/admin/payment-settings', $keep)
            ->assertOk()
            ->assertJsonPath('data.status.secret_key_configured', true)
            ->assertJsonPath('data.status.webhook_secret_configured', true);

        $clear = $keep;
        $clear['clear_secrets'] = ['secret_key', 'webhook_secret'];
        $this->putJson('/api/admin/payment-settings', $clear)
            ->assertOk()
            ->assertJsonPath('data.status.secret_key_configured', false)
            ->assertJsonPath('data.status.webhook_secret_configured', false);
    }

    public function test_validation_blocks_incomplete_or_mode_mismatched_live_configuration(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->putJson('/api/admin/payment-settings', $this->validSettings([
            'mode' => 'live',
            'publishable_key' => 'pk_test_wrong',
            'secret_key' => 'sk_test_wrong',
            'connect_refresh_url' => 'http://localhost/refresh',
            'connect_return_url' => 'http://localhost/return',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publishable_key', 'secret_key', 'connect_refresh_url', 'connect_return_url']);

        $this->putJson('/api/admin/payment-settings', $this->validSettings([
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publishable_key', 'secret_key', 'webhook_secret']);
    }

    public function test_dashboard_configuration_is_runtime_source_and_connection_test_is_safe(): void
    {
        config([
            'stripe.enabled' => false,
            'stripe.secret_key' => 'sk_test_environment',
            'stripe.publishable_key' => 'pk_test_environment',
            'stripe.webhook_secret' => 'whsec_environment',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/payment-settings', $this->validSettings())->assertOk();

        $configuration = app(PaymentConfigurationService::class);
        $this->assertSame('sk_test_admin_123456789', $configuration->secretKey());
        $this->assertSame('pk_test_admin_123456789', $configuration->publishableKey());
        $this->assertSame('dashboard', $configuration->source());

        $this->postJson('/api/admin/payment-settings/test')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.livemode', false)
            ->assertJsonPath('data.available_currencies.0', 'mad');
        $this->assertSame(1, $this->gateway->connectionTests);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_connection_test_succeeded']);
    }

    public function test_non_admin_cannot_manage_payment_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'rider']));

        $this->getJson('/api/admin/payment-settings')->assertForbidden();
        $this->putJson('/api/admin/payment-settings', $this->validSettings())->assertForbidden();
        $this->postJson('/api/admin/payment-settings/test')->assertForbidden();
    }

    public function test_admin_can_set_the_price_of_one_driver_point(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $payload = $this->validSettings(['point_price' => 2.5]);
        unset($payload['points_per_currency_unit']);

        $this->putJson('/api/admin/payment-settings', $payload)
            ->assertOk()
            ->assertJsonPath('data.values.point_price', 2.5)
            ->assertJsonPath('data.values.points_per_currency_unit', 0.4);

        $this->assertSame(2.5, app(PaymentConfigurationService::class)->pointPrice());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_settings_updated']);
    }

    public function test_admin_can_use_us_dollars_for_card_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/payment-settings', $this->validSettings(['currency' => 'USD']))
            ->assertOk()
            ->assertJsonPath('data.values.currency', 'USD');

        $this->assertSame('usd', app(PaymentConfigurationService::class)->currency());
    }

    /** @param array<string, mixed> $overrides */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'test',
            'publishable_key' => 'pk_test_admin_123456789',
            'secret_key' => 'sk_test_admin_123456789',
            'webhook_secret' => 'whsec_admin_123456789',
            'currency' => 'MAD',
            'points_min_topup' => 20,
            'points_max_topup' => 5000,
            'points_per_currency_unit' => 1,
            'connect_enabled' => true,
            'connect_country' => 'FR',
            'connect_refresh_url' => 'http://localhost:8081/payments/connect/refresh',
            'connect_return_url' => 'http://localhost:8081/payments/connect/return',
            'clear_secrets' => [],
        ], $overrides);
    }
}

class AdminPaymentSettingsGateway implements PaymentGateway
{
    public int $connectionTests = 0;

    public function verifyConnection(): array
    {
        $this->connectionTests++;

        return ['livemode' => false, 'available_currencies' => ['mad']];
    }

    public function createPaymentIntent(array $parameters, string $idempotencyKey): array
    {
        return [];
    }

    public function retrievePaymentIntent(string $providerIntentId): array
    {
        return [];
    }

    public function createRefund(array $parameters, string $idempotencyKey): array
    {
        return [];
    }

    public function createConnectedAccount(array $parameters, string $idempotencyKey): array
    {
        return [];
    }

    public function retrieveConnectedAccount(string $providerAccountId): array
    {
        return [];
    }

    public function createAccountLink(array $parameters): array
    {
        return [];
    }

    public function createTransfer(array $parameters, string $idempotencyKey): array
    {
        return [];
    }
}
