<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\FlagOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['city', 'driver', 'requester', 'transaction'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', $request->integer('city_id')))
            ->when($request->boolean('flagged'), fn ($q) => $q->whereNotNull('flagged_at'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return $this->ok(OrderResource::collection($orders)->response()->getData(true));
    }

    public function show(Order $order)
    {
        return $this->ok(new OrderResource(
            $order->load(['city', 'requester', 'concierge', 'driver', 'offers', 'transaction', 'statusEvents'])
        ));
    }

    public function cancel(CancelOrderRequest $request, Order $order, OrderService $orderService)
    {
        $order = $orderService->cancelByAdmin($request->user(), $order, $request->validated()['reason']);

        return $this->ok(new OrderResource($order), 'Order cancelled');
    }

    public function flag(FlagOrderRequest $request, Order $order, OrderService $orderService)
    {
        $order = $orderService->flagOrder($request->user(), $order, $request->validated()['reason']);

        return $this->ok(new OrderResource($order), 'Order flagged');
    }
}
