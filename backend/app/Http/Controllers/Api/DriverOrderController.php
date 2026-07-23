<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Orders\CounterOfferRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\WalletResource;
use App\Models\Order;
use App\Services\DriverDispatchService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriverOrderController extends ApiController
{
    public function online(Request $request)
    {
        $profile = $request->user()->driverProfile;

        if (! $profile
            || $profile->approval_state !== 'approved'
            || $profile->blocked_at !== null
            || $request->user()->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Driver must be active, approved, and unblocked before going online.', 'data' => null], 422);
        }

        $data = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'current_lat' => ['nullable', 'required_with:city_id', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'required_with:city_id', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($profile, $request, $data) {
            $profile->update(array_merge(
                Arr::only($data, ['current_lat', 'current_lng']),
                ['online_status' => true]
            ));

            if (isset($data['city_id'])) {
                DB::table('driver_locations')->insert([
                    'driver_id' => $request->user()->id,
                    'city_id' => $data['city_id'],
                    'lat' => $data['current_lat'],
                    'lng' => $data['current_lng'],
                    'reported_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $this->ok($profile->fresh(), 'Driver online');
    }

    public function offline(Request $request)
    {
        $request->user()->driverProfile?->update(['online_status' => false]);

        return $this->ok($request->user()->driverProfile?->fresh(), 'Driver offline');
    }

    public function index(Request $request, DriverDispatchService $dispatchService)
    {
        try {
            return $this->ok(OrderResource::collection($dispatchService->getAvailableOrders($request->user())));
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function current(Request $request)
    {
        $order = Order::query()
            ->with(['city', 'driver.driverProfile', 'offers', 'transaction'])
            ->where('assigned_driver_id', $request->user()->id)
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_ARRIVED, Order::STATUS_IN_PROGRESS])
            ->latest('assigned_at')
            ->first();

        return $this->ok($order ? new OrderResource($order) : null);
    }

    public function history(Request $request)
    {
        $orders = Order::query()
            ->with(['city', 'transaction', 'rating'])
            ->where('assigned_driver_id', $request->user()->id)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return $this->ok(OrderResource::collection($orders));
    }

    public function conversations(Request $request)
    {
        $orders = Order::query()
            ->with(['city', 'latestMessage.sender'])
            ->withCount('messages')
            ->where('assigned_driver_id', $request->user()->id)
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

    public function show(Order $order, Request $request, DriverDispatchService $dispatchService)
    {
        $visibleOrder = $dispatchService->getVisibleOrder($order, $request->user());

        if ($visibleOrder === null) {
            return response()->json(['success' => false, 'message' => 'Order not found.', 'data' => null], 404);
        }

        return $this->ok(new OrderResource($visibleOrder));
    }

    public function accept(Order $order, Request $request, DriverDispatchService $dispatchService)
    {
        try {
            return $this->ok(new OrderResource($dispatchService->acceptOrder($order, $request->user())), 'Order accepted');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function counter(Order $order, CounterOfferRequest $request, DriverDispatchService $dispatchService)
    {
        try {
            return $this->ok($dispatchService->counterOrder(
                $order,
                $request->user(),
                (float) $request->validated('amount'),
                $request->validated('message')
            ), 'Counter offer sent');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function decline(Order $order, Request $request, DriverDispatchService $dispatchService)
    {
        try {
            return $this->ok($dispatchService->declineOrder($order, $request->user(), $request->input('message')), 'Order declined');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function arrived(Order $order, Request $request, OrderService $orderService)
    {
        return $this->ok(new OrderResource($orderService->markArrived($order, $request->user())), 'Driver arrived');
    }

    public function start(Order $order, Request $request, OrderService $orderService)
    {
        return $this->ok(new OrderResource($orderService->startRide($order, $request->user())), 'Ride started');
    }

    public function complete(Order $order, Request $request, OrderService $orderService)
    {
        try {
            return $this->ok(new OrderResource($orderService->completeRide($order, $request->user())), 'Ride completed');
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
        }
    }

    public function wallet(Request $request)
    {
        return $this->ok(new WalletResource($request->user()->wallet()->with('ledgerEntries')->first()));
    }
}
