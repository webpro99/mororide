<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParticipantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_and_assigned_driver_can_exchange_and_list_messages(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder($rider, $driver);

        Sanctum::actingAs($rider);
        $riderResponse = $this->postJson("/api/orders/{$order->id}/messages", [
            'text' => 'I am next to the main entrance.',
            'sender_role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('data.sender_id', $rider->id)
            ->assertJsonPath('data.sender_role', 'rider')
            ->assertJsonPath('data.text', 'I am next to the main entrance.');

        Sanctum::actingAs($driver);
        $driverResponse = $this->postJson("/api/orders/{$order->id}/messages", [
            'image_url' => 'https://cdn.example.test/car.jpg',
        ])->assertCreated()
            ->assertJsonPath('data.sender_id', $driver->id)
            ->assertJsonPath('data.sender_role', 'driver')
            ->assertJsonPath('data.image_url', 'https://cdn.example.test/car.jpg');

        $this->assertDatabaseHas('chat_messages', [
            'id' => $riderResponse->json('data.id'),
            'order_id' => $order->id,
            'sender_id' => $rider->id,
            'sender_role' => 'rider',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'id' => $driverResponse->json('data.id'),
            'order_id' => $order->id,
            'sender_id' => $driver->id,
            'sender_role' => 'driver',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $driver->id,
            'type' => 'chat_message',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rider->id,
            'type' => 'chat_message',
        ]);

        $this->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.sender_role', 'rider')
            ->assertJsonPath('data.messages.1.sender_role', 'driver')
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    public function test_concierge_requester_can_chat_and_read_messages_after_ride_completion(): void
    {
        $concierge = User::factory()->create(['role' => 'concierge']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder(
            $concierge,
            $driver,
            source: 'concierge',
            status: Order::STATUS_COMPLETED
        );

        Sanctum::actingAs($concierge);
        $this->postJson("/api/orders/{$order->id}/messages", ['text' => 'Thank you.'])
            ->assertCreated()
            ->assertJsonPath('data.sender_role', 'concierge');

        $this->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.text', 'Thank you.');
    }

    public function test_non_participants_and_admin_cannot_read_or_send_messages(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder($rider, $driver);
        ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $rider->id,
            'sender_role' => 'rider',
            'text' => 'Private ride message',
        ]);

        $outsiders = [
            User::factory()->create(['role' => 'rider']),
            User::factory()->create(['role' => 'concierge']),
            User::factory()->create(['role' => 'driver']),
            User::factory()->create(['role' => 'admin']),
        ];

        foreach ($outsiders as $outsider) {
            Sanctum::actingAs($outsider);
            $this->getJson("/api/orders/{$order->id}/messages")->assertNotFound();
            // Authorization runs before validation so private orders cannot be
            // probed by deliberately sending an invalid message payload.
            $this->postJson("/api/orders/{$order->id}/messages", [])
                ->assertNotFound();
        }

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_chat_is_unavailable_until_a_driver_is_assigned(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $order = $this->makeUnassignedOrder($rider);

        Sanctum::actingAs($rider);
        $this->getJson("/api/orders/{$order->id}/messages")->assertNotFound();
        $this->postJson("/api/orders/{$order->id}/messages", ['text' => 'Anyone there?'])
            ->assertNotFound();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_message_requires_valid_text_or_https_or_http_image_url(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder($rider, $driver);

        Sanctum::actingAs($rider);
        $this->postJson("/api/orders/{$order->id}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['text', 'image_url']);

        $this->postJson("/api/orders/{$order->id}/messages", [
            'text' => str_repeat('a', 2001),
            'image_url' => 'https://example.test/'.str_repeat('a', 240).'.jpg',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['text', 'image_url']);

        $this->postJson("/api/orders/{$order->id}/messages", [
            'image_url' => 'ftp://example.test/image.jpg',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['image_url']);

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_listing_is_paginated_and_ordered_oldest_first(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder($rider, $driver);

        foreach (range(1, 51) as $number) {
            ChatMessage::create([
                'order_id' => $order->id,
                'sender_id' => $rider->id,
                'sender_role' => 'rider',
                'text' => "Message {$number}",
            ]);
        }

        Sanctum::actingAs($driver);
        $this->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonCount(50, 'data.messages')
            ->assertJsonPath('data.messages.0.text', 'Message 1')
            ->assertJsonPath('data.messages.49.text', 'Message 50')
            ->assertJsonPath('data.pagination.total', 51)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson("/api/orders/{$order->id}/messages?page=2")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.text', 'Message 51')
            ->assertJsonPath('data.pagination.current_page', 2);
    }

    public function test_chat_endpoints_require_authentication(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->makeAssignedOrder($rider, $driver);

        $this->getJson("/api/orders/{$order->id}/messages")->assertUnauthorized();
        $this->postJson("/api/orders/{$order->id}/messages", ['text' => 'Hello'])
            ->assertUnauthorized();
    }

    private function makeAssignedOrder(
        User $requester,
        User $driver,
        string $source = 'rider',
        string $status = Order::STATUS_ASSIGNED
    ): Order {
        return Order::create([
            'source' => $source,
            'requester_id' => $requester->id,
            'concierge_id' => $source === 'concierge' ? $requester->id : null,
            'city_id' => City::create(['name' => fake()->unique()->city(), 'is_active' => true])->id,
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Dropoff',
            'distance_km' => 5,
            'eta_min' => 12,
            'offered_fare' => 100,
            'status' => $status,
            'assigned_driver_id' => $driver->id,
            'assigned_at' => now(),
            'completed_at' => $status === Order::STATUS_COMPLETED ? now() : null,
        ]);
    }

    private function makeUnassignedOrder(User $requester): Order
    {
        return Order::create([
            'source' => 'rider',
            'requester_id' => $requester->id,
            'city_id' => City::create(['name' => fake()->unique()->city(), 'is_active' => true])->id,
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Dropoff',
            'distance_km' => 5,
            'eta_min' => 12,
            'offered_fare' => 100,
            'status' => Order::STATUS_SEARCHING,
        ]);
    }
}
