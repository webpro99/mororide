<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use RuntimeException;
use Stripe\StripeClient;

class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    private ?string $clientSecret = null;

    public function __construct(private PaymentConfigurationService $configuration) {}

    public function verifyConnection(): array
    {
        $balance = $this->client()->balance->retrieve()->toArray();
        $currencies = array_merge(
            array_column($balance['available'] ?? [], 'currency'),
            array_column($balance['pending'] ?? [], 'currency')
        );

        return [
            'livemode' => (bool) ($balance['livemode'] ?? false),
            'available_currencies' => array_values(array_filter(array_unique($currencies))),
        ];
    }

    public function createPaymentIntent(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->paymentIntents
            ->create($parameters, ['idempotency_key' => $idempotencyKey])
            ->toArray();
    }

    public function retrievePaymentIntent(string $providerIntentId): array
    {
        return $this->client()->paymentIntents->retrieve($providerIntentId)->toArray();
    }

    public function createRefund(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->refunds
            ->create($parameters, ['idempotency_key' => $idempotencyKey])
            ->toArray();
    }

    public function createConnectedAccount(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->accounts
            ->create($parameters, ['idempotency_key' => $idempotencyKey])
            ->toArray();
    }

    public function retrieveConnectedAccount(string $providerAccountId): array
    {
        return $this->client()->accounts->retrieve($providerAccountId)->toArray();
    }

    public function createAccountLink(array $parameters): array
    {
        return $this->client()->accountLinks->create($parameters)->toArray();
    }

    public function createTransfer(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->transfers
            ->create($parameters, ['idempotency_key' => $idempotencyKey])
            ->toArray();
    }

    private function client(): StripeClient
    {
        $secret = $this->configuration->secretKey();

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        if ($this->client === null || $this->clientSecret !== $secret) {
            $this->client = new StripeClient($secret);
            $this->clientSecret = $secret;
        }

        return $this->client;
    }
}
