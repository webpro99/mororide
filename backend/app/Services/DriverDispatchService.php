<?php

namespace App\Services;

use App\Events\OrderOfferSubmitted;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriverDispatchService
{
    public function __construct(private OrderService $orderService) {}

    public function getNearbyOnlineDrivers(int $cityId): Collection
    {
        return User::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->whereHas('driverProfile', function ($query) {
                $query->where('approval_state', 'approved')->where('online_status', true);
            })
            ->whereExists(function ($query) use ($cityId) {
                $query->selectRaw('1')
                    ->from('driver_locations as current_location')
                    ->whereColumn('current_location.driver_id', 'users.id')
                    ->where('current_location.city_id', $cityId)
                    ->whereNotExists(function ($newerLocation) {
                        $newerLocation->selectRaw('1')
                            ->from('driver_locations as newer_location')
                            ->whereColumn('newer_location.driver_id', 'current_location.driver_id')
                            ->where(function ($newer) {
                                $newer->whereColumn('newer_location.reported_at', '>', 'current_location.reported_at')
                                    ->orWhere(function ($sameTimestamp) {
                                        $sameTimestamp
                                            ->whereColumn('newer_location.reported_at', '=', 'current_location.reported_at')
                                            ->whereColumn('newer_location.id', '>', 'current_location.id');
                                    });
                            });
                    });
            })
            ->with('driverProfile')
            ->get();
    }

    public function getAvailableOrders(User $driver): LengthAwarePaginator
    {
        $this->ensureDriverCanReceiveOrders($driver);

        $orders = Order::query()
            ->with(['city', 'offers'])
            ->whereIn('status', [Order::STATUS_SEARCHING, Order::STATUS_OFFERED])
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereDoesntHave('offers', function (Builder $query) use ($driver) {
                $query->where('driver_id', $driver->id)->where('type', 'decline');
            });

        if (($cityId = $this->latestDriverCityId($driver)) !== null) {
            $orders->where('city_id', $cityId);
        }

        return $orders->latest()->paginate(20);
    }

    public function getVisibleOrder(Order $order, User $driver): ?Order
    {
        if (in_array($order->status, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)) {
            try {
                $this->ensureDriverCanReceiveOrders($driver);
                $this->ensureOrderIsAvailableToDriver($order, $driver);
            } catch (RuntimeException) {
                return null;
            }
        } elseif ((int) $order->assigned_driver_id !== (int) $driver->id) {
            return null;
        }

        return $order->load(['city', 'offers', 'driver', 'transaction']);
    }

    public function acceptOrder(Order $order, User $driver): Order
    {
        [$assignedOrder, $offer] = DB::transaction(function () use ($order, $driver) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $this->ensureDriverCanReceiveOrders($driver);
            $this->ensureOrderIsAvailableToDriver($lockedOrder, $driver);

            $offer = OrderOffer::create([
                'order_id' => $lockedOrder->id,
                'driver_id' => $driver->id,
                'type' => 'accept',
                'amount' => $lockedOrder->offered_fare,
                'status' => 'accepted',
            ]);

            return [
                $this->orderService->assignDriver($lockedOrder, $driver, (float) $lockedOrder->offered_fare),
                $offer,
            ];
        });

        OrderOfferSubmitted::dispatch($offer);

        return $assignedOrder;
    }

    public function counterOrder(Order $order, User $driver, float $amount, ?string $message = null): OrderOffer
    {
        $offer = DB::transaction(function () use ($order, $driver, $amount, $message) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $this->ensureDriverCanReceiveOrders($driver);
            $this->ensureOrderIsAvailableToDriver($lockedOrder, $driver);

            $lockedOrder->update(['status' => Order::STATUS_OFFERED]);

            return OrderOffer::create([
                'order_id' => $lockedOrder->id,
                'driver_id' => $driver->id,
                'type' => 'counter',
                'amount' => $amount,
                'message' => $message,
                'status' => 'pending',
            ]);
        });

        OrderOfferSubmitted::dispatch($offer);

        return $offer;
    }

    public function declineOrder(Order $order, User $driver, ?string $message = null): OrderOffer
    {
        $offer = DB::transaction(function () use ($order, $driver, $message) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $this->ensureDriverCanReceiveOrders($driver);
            $this->ensureOrderIsAvailableToDriver($lockedOrder, $driver);

            return OrderOffer::create([
                'order_id' => $lockedOrder->id,
                'driver_id' => $driver->id,
                'type' => 'decline',
                'message' => $message,
                'status' => 'declined',
            ]);
        });

        OrderOfferSubmitted::dispatch($offer);

        return $offer;
    }

    private function ensureDriverCanReceiveOrders(User $driver): void
    {
        $profile = $driver->driverProfile;

        if ($driver->role !== 'driver' || ! $profile || $driver->status !== 'active' || $profile->approval_state !== 'approved' || ! $profile->online_status) {
            throw new RuntimeException('Driver must be approved and online.');
        }
    }

    private function ensureOrderIsAvailableToDriver(Order $order, User $driver): void
    {
        if (! in_array($order->status, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)) {
            throw new RuntimeException('Order is not available for assignment.');
        }

        if ($order->expires_at !== null && ! $order->expires_at->isFuture()) {
            throw new RuntimeException('Order has expired.');
        }

        $driverCityId = $this->latestDriverCityId($driver);

        if ($driverCityId !== null && $driverCityId !== (int) $order->city_id) {
            throw new RuntimeException('Order is outside the driver city.');
        }

        if (OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->where('type', 'decline')
            ->exists()) {
            throw new RuntimeException('Driver already declined this order.');
        }
    }

    private function latestDriverCityId(User $driver): ?int
    {
        $cityId = DB::table('driver_locations')
            ->where('driver_id', $driver->id)
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->value('city_id');

        return $cityId === null ? null : (int) $cityId;
    }
}
