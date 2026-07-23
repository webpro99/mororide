<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Orders\CancelParticipantOrderRequest;
use App\Http\Requests\Orders\ChooseDriverRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\StoreRatingRequest;
use App\Http\Resources\OrderOfferResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RatingResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ParticipantOrderService;
use Illuminate\Http\Request;
use RuntimeException;

class ConciergeOrderController extends ApiController
{
    public function index(Request $request)
    {
        $orders = Order::with(['city', 'driver', 'offers', 'transaction', 'rating'])
            ->where('concierge_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->ok(OrderResource::collection($orders));
    }

    public function store(StoreOrderRequest $request, OrderService $orderService)
    {
        $order = $orderService->createConciergeOrder($request->user(), $request->validated());

        return $this->ok(new OrderResource($order), 'Concierge order created', 201);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless(
            $order->source === 'concierge'
            && (int) $order->concierge_id === (int) $request->user()->id,
            404
        );

        return $this->ok(new OrderResource($order->load(['city', 'driver', 'offers', 'transaction', 'rating'])));
    }

    public function offers(Request $request, Order $order, ParticipantOrderService $participantOrders)
    {
        return $this->ok(OrderOfferResource::collection(
            $participantOrders->getOffers($order, $request->user(), 'concierge')
        ));
    }

    public function chooseDriver(
        ChooseDriverRequest $request,
        Order $order,
        ParticipantOrderService $participantOrders
    ) {
        try {
            $order = $participantOrders->chooseDriver(
                $order,
                $request->user(),
                'concierge',
                (int) $request->validated('offer_id')
            );

            return $this->ok(new OrderResource($order), 'Driver selected');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function cancel(
        CancelParticipantOrderRequest $request,
        Order $order,
        ParticipantOrderService $participantOrders
    ) {
        try {
            $order = $participantOrders->cancel(
                $order,
                $request->user(),
                'concierge',
                $request->validated('reason')
            );

            return $this->ok(new OrderResource($order), 'Order cancelled');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function rating(
        StoreRatingRequest $request,
        Order $order,
        ParticipantOrderService $participantOrders
    ) {
        try {
            $rating = $participantOrders->rate(
                $order,
                $request->user(),
                'concierge',
                (int) $request->validated('score'),
                $request->validated('comment')
            );

            return $this->ok(new RatingResource($rating), 'Rating submitted', 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }
}
