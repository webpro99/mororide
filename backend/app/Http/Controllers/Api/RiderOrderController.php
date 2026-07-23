<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Orders\CancelParticipantOrderRequest;
use App\Http\Requests\Orders\ChooseDriverRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\StoreRatingRequest;
use App\Http\Resources\OrderOfferResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\RatingResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ParticipantOrderService;
use Illuminate\Http\Request;
use RuntimeException;

class RiderOrderController extends ApiController
{
    public function index(Request $request)
    {
        $orders = Order::with(['city', 'driver', 'offers', 'transaction', 'rating'])
            ->where('requester_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->ok(OrderResource::collection($orders));
    }

    public function history(Request $request)
    {
        $orders = Order::query()
            ->with(['city', 'driver', 'transaction', 'rating'])
            ->where('source', 'rider')
            ->where('requester_id', $request->user()->id)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return $this->ok(OrderResource::collection($orders));
    }

    public function conversations(Request $request)
    {
        $orders = Order::query()
            ->with(['city', 'driver', 'latestMessage.sender'])
            ->withCount('messages')
            ->where('source', 'rider')
            ->where('requester_id', $request->user()->id)
            ->whereHas('messages')
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $conversations = $orders->map(fn (Order $order) => [
            'order' => (new OrderResource($order))->resolve($request),
            'messages_count' => $order->messages_count,
            'last_message' => $order->latestMessage
                ? (new ChatMessageResource($order->latestMessage))->resolve($request)
                : null,
        ]);

        return $this->ok($conversations);
    }

    public function store(StoreOrderRequest $request, OrderService $orderService)
    {
        $order = $orderService->createRiderOrder($request->user(), $request->validated());

        return $this->ok(new OrderResource($order), 'Order created', 201);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless(
            $order->source === 'rider'
            && (int) $order->requester_id === (int) $request->user()->id,
            404
        );

        return $this->ok(new OrderResource($order->load(['city', 'driver', 'offers', 'transaction', 'rating'])));
    }

    public function offers(Request $request, Order $order, ParticipantOrderService $participantOrders)
    {
        return $this->ok(OrderOfferResource::collection(
            $participantOrders->getOffers($order, $request->user(), 'rider')
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
                'rider',
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
                'rider',
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
                'rider',
                (int) $request->validated('score'),
                $request->validated('comment')
            );

            return $this->ok(new RatingResource($rating), 'Rating submitted', 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }
}
