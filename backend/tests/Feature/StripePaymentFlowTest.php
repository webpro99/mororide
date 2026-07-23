<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\City;
use App\Models\FareConfig;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripePaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'null',
            'stripe.enabled' => true,
            'stripe.secret_key' => 'sk_test_fake',
            'stripe.publishable_key' => 'pk_test_fake',
            'stripe.webhook_secret' => 'whsec_test_secret',
            'stripe.currency' => 'mad',
            'stripe.connect.enabled' => true,
            'stripe.connect.country' => 'FR',
        ]);

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->city = City::create(['name' => 'Marrakech', 'is_active' => true]);
        FareConfig::create([
            'base' => 10,
            'per_km' => 2,
            'per_min' => 1,
            'per_pax' => 0,
            'floor' => 20,
            'platform_fee_pct' => 0.15,
            'currency' => 'MAD',
            'is_active' => true,
        ]);
    }

    public function test_ride_intent_is_owner_only_and_idempotent(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $other = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->cardOrder($rider, $driver, Order::STATUS_ASSIGNED);

        Sanctum::actingAs($other);
        $this->postJson("/api/payments/orders/{$order->id}/intent")->assertNotFound();

        Sanctum::actingAs($rider);
        $first = $this->postJson("/api/payments/orders/{$order->id}/intent")
            ->assertCreated()
            ->assertJsonPath('data.amount_minor', 10000)
            ->assertJsonPath('data.currency', 'mad')
            ->assertJsonPath('data.publishable_key', 'pk_test_fake')
            ->assertJsonPath('data.client_secret', 'pi_test_1_secret_fake');

        $this->postJson("/api/payments/orders/{$order->id}/intent")
            ->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, $this->gateway->paymentIntentCreates);
        $this->assertSame(1, $this->gateway->paymentIntentRetrieves);
        $this->assertSame(1, PaymentIntent::where('order_id', $order->id)->count());
    }

    public function test_signed_webhook_unlocks_card_completion_and_driver_credit_once(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->cardOrder($rider, $driver, Order::STATUS_IN_PROGRESS);

        Sanctum::actingAs($rider);
        $this->postJson("/api/payments/orders/{$order->id}/intent")->assertCreated();

        Sanctum::actingAs($driver);
        $this->postJson("/api/driver/orders/{$order->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The card payment for the current fare has not succeeded yet.');

        $event = $this->paymentEvent('evt_ride_succeeded', 'payment_intent.succeeded', 'pi_test_1');
        $this->sendSignedWebhook($event)->assertOk()->assertJson(['received' => true, 'handled' => true]);
        $this->sendSignedWebhook($event)->assertOk()->assertJson(['received' => true, 'handled' => false]);

        Sanctum::actingAs($driver);
        $this->postJson("/api/driver/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

        $this->assertSame(85.0, (float) $driver->wallet()->first()->wallet_balance);
        $this->assertDatabaseHas('transactions', [
            'order_id' => $order->id,
            'status' => 'succeeded',
        ]);
        $this->assertSame('stripe', $order->fresh()->transaction->metadata['mode']);
        $this->assertSame('pi_test_1', $order->fresh()->transaction->payment_provider_references['payment_intent_id']);
        $this->assertSame(1, WalletLedgerEntry::where('order_id', $order->id)->where('entry_type', 'card_net_earning')->count());
    }

    public function test_points_are_credited_once_only_after_a_valid_succeeded_webhook(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->withHeader('Idempotency-Key', 'points-topup-request-0001')
            ->postJson('/api/driver/payments/points/intent', ['amount' => 50])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requires_payment_method');

        $this->assertNull($driver->wallet);

        $event = $this->paymentEvent('evt_points_succeeded', 'payment_intent.succeeded', 'pi_test_1');
        $this->sendSignedWebhook($event)->assertOk();
        $this->sendSignedWebhook($event)->assertOk();

        $this->assertSame(50.0, (float) $driver->fresh()->wallet->points_balance);
        $intent = PaymentIntent::where('provider_intent_id', 'pi_test_1')->firstOrFail();
        $this->assertSame(1, WalletLedgerEntry::where('payment_intent_id', $intent->id)->where('entry_type', 'points_topup')->count());
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt_points_succeeded',
            'status' => 'processed',
            'attempts' => 1,
        ]);
    }

    public function test_driver_sees_admin_point_price_and_purchase_uses_it(): void
    {
        PlatformSetting::create([
            'key' => 'payment.points_per_currency_unit',
            'value' => '0.5',
            'type' => 'number',
            'group' => 'payments',
            'label' => 'Points per currency unit',
        ]);

        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/payments/points/config')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.point_price', 2)
            ->assertJsonPath('data.points_per_currency_unit', 0.5)
            ->assertJsonPath('data.minimum_points', 10);

        $this->withHeader('Idempotency-Key', 'admin-priced-points-0001')
            ->postJson('/api/driver/payments/points/intent', ['amount' => 50])
            ->assertCreated()
            ->assertJsonPath('data.amount', 50)
            ->assertJsonPath('data.points', 25)
            ->assertJsonPath('data.point_price', 2);

        $event = $this->paymentEvent('evt_admin_priced_points', 'payment_intent.succeeded', 'pi_test_1');
        $this->sendSignedWebhook($event)->assertOk();

        $this->assertSame(25.0, (float) $driver->fresh()->wallet->points_balance);
    }

    public function test_invalid_webhook_signature_is_rejected_without_persistence(): void
    {
        $event = $this->paymentEvent('evt_invalid', 'payment_intent.succeeded', 'pi_missing');

        $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid'],
            json_encode($event, JSON_THROW_ON_ERROR)
        )->assertBadRequest();

        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_connect_onboarding_and_admin_refund_use_provider_idempotency(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/payments/connect/onboarding')
            ->assertCreated()
            ->assertJsonPath('data.account.account_id', 'acct_test_1')
            ->assertJsonPath('data.onboarding_url', 'https://connect.stripe.test/onboard');

        $this->getJson('/api/driver/payments/connect/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        $admin = User::factory()->create(['role' => 'admin']);
        $intent = PaymentIntent::create([
            'user_id' => $driver->id,
            'provider' => 'stripe',
            'provider_intent_id' => 'pi_refund_1',
            'purpose' => PaymentIntent::PURPOSE_POINTS_TOPUP,
            'amount' => 100,
            'amount_minor' => 10000,
            'currency' => 'mad',
            'status' => 'succeeded',
            'idempotency_key' => 'direct-test-refund-intent',
        ]);

        Sanctum::actingAs($admin);
        $this->withHeader('Idempotency-Key', 'admin-refund-request-0001')
            ->postJson("/api/admin/payments/{$intent->id}/refund", [
                'amount' => 25,
                'reason' => 'requested_by_customer',
            ])
            ->assertOk()
            ->assertJsonPath('data.refund.amount_minor', 2500);

        $this->withHeader('Idempotency-Key', 'admin-refund-request-0001')
            ->postJson("/api/admin/payments/{$intent->id}/refund", [
                'amount' => 25,
                'reason' => 'requested_by_customer',
            ])
            ->assertOk()
            ->assertJsonPath('data.refund.id', 're_test_1');

        $this->assertSame(1, $this->gateway->refundCreates);
        $this->assertSame('refund_pending', $intent->fresh()->status);
    }

    public function test_disabled_stripe_returns_service_unavailable_without_gateway_call(): void
    {
        config(['stripe.enabled' => false]);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->cardOrder($rider, $driver, Order::STATUS_ASSIGNED);

        Sanctum::actingAs($rider);
        $this->postJson("/api/payments/orders/{$order->id}/intent")
            ->assertStatus(503)
            ->assertJsonPath('data', null);

        $this->assertSame(0, $this->gateway->paymentIntentCreates);
    }

    private function cardOrder(User $rider, User $driver, string $status): Order
    {
        return Order::create([
            'source' => 'rider',
            'requester_id' => $rider->id,
            'city_id' => $this->city->id,
            'pickup_address' => 'Medina',
            'dropoff_address' => 'Airport',
            'distance_km' => 10,
            'eta_min' => 20,
            'pax' => 1,
            'offered_fare' => 100,
            'final_fare' => 100,
            'payment_method' => 'card',
            'status' => $status,
            'assigned_driver_id' => $driver->id,
        ]);
    }

    private function paymentEvent(string $id, string $type, string $providerIntentId): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => $providerIntentId,
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                    'latest_charge' => 'ch_test_1',
                ],
            ],
        ];
    }

    private function sendSignedWebhook(array $event)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        return $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }
}

class FakePaymentGateway implements PaymentGateway
{
    public int $paymentIntentCreates = 0;

    public int $paymentIntentRetrieves = 0;

    public int $refundCreates = 0;

    private array $intents = [];

    public function verifyConnection(): array
    {
        return ['livemode' => false, 'available_currencies' => ['mad']];
    }

    public function createPaymentIntent(array $parameters, string $idempotencyKey): array
    {
        $this->paymentIntentCreates++;
        $id = 'pi_test_'.$this->paymentIntentCreates;

        return $this->intents[$id] = [
            'id' => $id,
            'client_secret' => $id.'_secret_fake',
            'status' => 'requires_payment_method',
            'amount' => $parameters['amount'],
            'currency' => $parameters['currency'],
        ];
    }

    public function retrievePaymentIntent(string $providerIntentId): array
    {
        $this->paymentIntentRetrieves++;

        return $this->intents[$providerIntentId];
    }

    public function createRefund(array $parameters, string $idempotencyKey): array
    {
        $this->refundCreates++;

        return [
            'id' => 're_test_'.$this->refundCreates,
            'status' => 'pending',
            'amount' => $parameters['amount'] ?? 10000,
            'currency' => 'mad',
        ];
    }

    public function createConnectedAccount(array $parameters, string $idempotencyKey): array
    {
        return [
            'id' => 'acct_test_1',
            'country' => $parameters['country'],
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'capabilities' => [],
            'requirements' => [],
        ];
    }

    public function retrieveConnectedAccount(string $providerAccountId): array
    {
        return [
            'id' => $providerAccountId,
            'country' => 'FR',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
            'requirements' => [],
        ];
    }

    public function createAccountLink(array $parameters): array
    {
        return [
            'url' => 'https://connect.stripe.test/onboard',
            'expires_at' => time() + 300,
        ];
    }

    public int $transferCreates = 0;

    public function createTransfer(array $parameters, string $idempotencyKey): array
    {
        $this->transferCreates++;

        return [
            'id' => 'tr_test_'.$this->transferCreates,
            'amount' => $parameters['amount'] ?? 0,
            'currency' => $parameters['currency'] ?? 'mad',
            'destination' => $parameters['destination'] ?? null,
        ];
    }
}
