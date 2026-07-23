<?php

namespace App\Http\Controllers\Api;

use App\Services\PaymentConfigurationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController
{
    public function __invoke(Request $request, PaymentService $payments, PaymentConfigurationService $configuration)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = $configuration->webhookSecret();

        if (! is_string($secret) || $secret === '') {
            return response()->json(['received' => false, 'message' => 'Stripe webhook secret is not configured.'], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret,
                $configuration->webhookTolerance()
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['received' => false, 'message' => 'Invalid Stripe webhook signature.'], 400);
        }

        try {
            $handled = $payments->handleWebhook($event->toArray(), $payload);
        } catch (Throwable $exception) {
            report($exception);

            // A non-2xx response tells Stripe to retry the same event later.
            return response()->json(['received' => false, 'message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true, 'handled' => $handled]);
    }
}
