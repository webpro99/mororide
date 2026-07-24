<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payments\CreatePointsTopupRequest;
use App\Services\BillingModeService;
use App\Services\PaymentService;
use App\Services\PaymentConfigurationService;
use Illuminate\Http\Request;
use RuntimeException;

class DriverPaymentController extends ApiController
{
    public function pointsConfig(PaymentConfigurationService $configuration, BillingModeService $billingMode)
    {
        if ($billingMode->freeLaunchEnabled()) {
            return $this->ok(array_merge($configuration->pointsPurchaseConfig(), [
                'available' => false,
                'disabled_reason' => 'Wallet and points are disabled while free launch mode is active.',
            ]));
        }

        return $this->ok($configuration->pointsPurchaseConfig());
    }

    public function pointsIntent(CreatePointsTopupRequest $request, PaymentService $payments)
    {
        try {
            return $this->ok($payments->createPointsTopup(
                $request->user(),
                (float) $request->validated('amount'),
                $request->validated('idempotency_key')
            ), 'Points payment intent ready', 201);
        } catch (RuntimeException $exception) {
            return $this->paymentError($exception);
        }
    }

    public function connectOnboarding(Request $request, PaymentService $payments)
    {
        try {
            return $this->ok($payments->createConnectOnboarding($request->user()), 'Stripe onboarding link ready', 201);
        } catch (RuntimeException $exception) {
            return $this->paymentError($exception);
        }
    }

    public function connectStatus(Request $request, PaymentService $payments)
    {
        try {
            return $this->ok($payments->getConnectStatus($request->user()));
        } catch (RuntimeException $exception) {
            return $this->paymentError($exception);
        }
    }

    private function paymentError(RuntimeException $exception)
    {
        $status = $exception->getCode() === 503 ? 503 : 422;

        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => null,
        ], $status);
    }
}
