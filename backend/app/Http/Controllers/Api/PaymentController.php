<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends ApiController
{
    public function rideIntent(Request $request, Order $order, PaymentService $payments)
    {
        $user = $request->user();
        $ownsOrder = ($order->source === 'rider' && $user->role === 'rider' && (int) $order->requester_id === (int) $user->id)
            || ($order->source === 'concierge' && $user->role === 'concierge' && (int) $order->concierge_id === (int) $user->id);

        abort_unless($ownsOrder, 404);

        try {
            return $this->ok($payments->createRidePayment($user, $order), 'Payment intent ready', 201);
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
