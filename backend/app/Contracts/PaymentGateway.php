<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function verifyConnection(): array;

    public function createPaymentIntent(array $parameters, string $idempotencyKey): array;

    public function retrievePaymentIntent(string $providerIntentId): array;

    public function createRefund(array $parameters, string $idempotencyKey): array;

    public function createConnectedAccount(array $parameters, string $idempotencyKey): array;

    public function retrieveConnectedAccount(string $providerAccountId): array;

    public function createAccountLink(array $parameters): array;

    public function createTransfer(array $parameters, string $idempotencyKey): array;
}
