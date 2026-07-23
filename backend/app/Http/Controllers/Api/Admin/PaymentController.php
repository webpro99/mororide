<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Payments\RefundPaymentRequest;
use App\Models\PaymentIntent;
use App\Services\PaymentService;
use RuntimeException;

class PaymentController extends ApiController
{
    public function refund(RefundPaymentRequest $request, PaymentIntent $paymentIntent, PaymentService $payments)
    {
        try {
            return $this->ok($payments->requestRefund(
                $paymentIntent,
                $request->validated('amount') === null ? null : (float) $request->validated('amount'),
                $request->validated('reason'),
                $request->validated('idempotency_key')
            ), 'Refund requested');
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() === 503 ? 503 : 422;

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], $status);
        }
    }
}
