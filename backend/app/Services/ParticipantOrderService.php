<?php

namespace App\Services;

use App\Events\OrderAssigned;
use App\Events\OrderStatusChanged;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\OrderStatusEvent;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParticipantOrderService
{
    private const CANCELLABLE_STATUSES = [
        Order::STATUS_SEARCHING,
        Order::STATUS_OFFERED,
        Order::STATUS_ASSIGNED,
        Order::STATUS_ARRIVED,
    ];

    public function __construct(
        private AuditLogService $auditLogService,
        private NotificationService $notificationService
    ) {}

    public function getOffers(Order $order, User $actor, string $source): Collection
    {
        $this->ensureOwner($order, $actor, $source);

        return $order->offers()
            ->whereIn('type', ['accept', 'counter'])
            ->with('driver.driverProfile')
            ->oldest()
            ->get();
    }

    public function chooseDriver(Order $order, User $actor, string $source, int $offerId): Order
    {
        $this->ensureOwner($order, $actor, $source);

        return DB::transaction(function () use ($order, $actor, $source, $offerId) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOwner($lockedOrder, $actor, $source);

            if (! in_array($lockedOrder->status, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)
                || $lockedOrder->assigned_driver_id !== null) {
                throw new RuntimeException('Order is no longer available for driver selection.');
            }

            if ($lockedOrder->expires_at !== null && ! $lockedOrder->expires_at->isFuture()) {
                throw new RuntimeException('Order has expired.');
            }

            $offer = OrderOffer::query()
                ->whereKey($offerId)
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $offer
                || $offer->status !== 'pending'
                || ! in_array($offer->type, ['accept', 'counter'], true)
                || $offer->amount === null
                || (float) $offer->amount <= 0) {
                throw new RuntimeException('A pending accept or counter offer from this order is required.');
            }

            $driver = User::query()->lockForUpdate()->find($offer->driver_id);
            $driverProfile = DriverProfile::query()
                ->where('user_id', $offer->driver_id)
                ->lockForUpdate()
                ->first();

            if (! $driver
                || $driver->role !== 'driver'
                || $driver->status !== 'active'
                || ! $driverProfile
                || $driverProfile->approval_state !== 'approved'
                || ! $driverProfile->online_status) {
                throw new RuntimeException('Selected driver is no longer eligible for assignment.');
            }

            $oldStatus = $lockedOrder->status;
            $finalFare = round((float) $offer->amount, 2);

            $lockedOrder->update([
                'status' => Order::STATUS_ASSIGNED,
                'assigned_driver_id' => $offer->driver_id,
                'final_fare' => $finalFare,
                'assigned_at' => now(),
            ]);

            $offer->update(['status' => 'accepted']);

            OrderOffer::query()
                ->where('order_id', $lockedOrder->id)
                ->whereKeyNot($offer->id)
                ->whereIn('type', ['accept', 'counter'])
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

            OrderStatusEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'from_status' => $oldStatus,
                'to_status' => Order::STATUS_ASSIGNED,
                'note' => 'Requester selected a driver offer.',
            ]);

            OrderStatusChanged::dispatch(
                $lockedOrder,
                $actor->id,
                $oldStatus,
                Order::STATUS_ASSIGNED
            );
            OrderAssigned::dispatch($lockedOrder);
            $this->notificationService->push(
                $driver,
                'offer_accepted',
                'Your offer was accepted',
                "Your offer for ride #{$lockedOrder->id} was accepted. Open Current Ride to continue.",
                [
                    'order_id' => $lockedOrder->id,
                    'status' => Order::STATUS_ASSIGNED,
                    'screen' => 'current_ride',
                ]
            );
            $this->notificationService->push(
                $actor,
                'driver_selected',
                'Driver offer accepted',
                "You selected {$driver->name} for ride #{$lockedOrder->id}. You can start chatting now.",
                [
                    'order_id' => $lockedOrder->id,
                    'driver_id' => $driver->id,
                    'status' => Order::STATUS_ASSIGNED,
                    'screen' => 'tracking',
                ]
            );

            $this->auditLogService->record(
                $actor,
                'requester_selected_driver',
                $lockedOrder,
                ['status' => $oldStatus, 'assigned_driver_id' => null],
                [
                    'status' => Order::STATUS_ASSIGNED,
                    'assigned_driver_id' => $offer->driver_id,
                    'offer_id' => $offer->id,
                    'final_fare' => $finalFare,
                ]
            );

            return $lockedOrder->fresh(['city', 'driver.driverProfile', 'offers', 'transaction', 'rating']);
        });
    }

    public function cancel(Order $order, User $actor, string $source, ?string $reason = null): Order
    {
        $this->ensureOwner($order, $actor, $source);

        return DB::transaction(function () use ($order, $actor, $source, $reason) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOwner($lockedOrder, $actor, $source);

            if (! in_array($lockedOrder->status, self::CANCELLABLE_STATUSES, true)) {
                throw new RuntimeException('Order can no longer be cancelled by its requester.');
            }

            $oldStatus = $lockedOrder->status;
            $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            OrderOffer::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('type', ['accept', 'counter'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

            OrderStatusEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'from_status' => $oldStatus,
                'to_status' => Order::STATUS_CANCELLED,
                'note' => $reason === null
                    ? 'Order cancelled by requester.'
                    : 'Order cancelled by requester: '.$reason,
            ]);

            OrderStatusChanged::dispatch(
                $lockedOrder,
                $actor->id,
                $oldStatus,
                Order::STATUS_CANCELLED
            );

            $this->auditLogService->record(
                $actor,
                'requester_cancelled_order',
                $lockedOrder,
                ['status' => $oldStatus],
                ['status' => Order::STATUS_CANCELLED, 'reason' => $reason]
            );

            return $lockedOrder->fresh(['city', 'driver.driverProfile', 'offers', 'transaction', 'rating']);
        });
    }

    public function rate(
        Order $order,
        User $actor,
        string $source,
        int $score,
        ?string $comment = null
    ): Rating {
        $this->ensureOwner($order, $actor, $source);

        return DB::transaction(function () use ($order, $actor, $source, $score, $comment) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOwner($lockedOrder, $actor, $source);

            if ($lockedOrder->status !== Order::STATUS_COMPLETED || $lockedOrder->assigned_driver_id === null) {
                throw new RuntimeException('Only completed orders with an assigned driver can be rated.');
            }

            if (Rating::query()->where('order_id', $lockedOrder->id)->exists()) {
                throw new RuntimeException('This order has already been rated.');
            }

            $comment = $comment === null || trim($comment) === '' ? null : trim($comment);

            $rating = Rating::create([
                'order_id' => $lockedOrder->id,
                'rater_id' => $actor->id,
                'driver_id' => $lockedOrder->assigned_driver_id,
                'score' => $score,
                'comment' => $comment,
            ]);

            $this->auditLogService->record(
                $actor,
                'requester_rated_driver',
                $rating,
                [],
                ['order_id' => $lockedOrder->id, 'driver_id' => $lockedOrder->assigned_driver_id, 'score' => $score]
            );

            return $rating->load('driver:id,name');
        });
    }

    private function ensureOwner(Order $order, User $actor, string $source): void
    {
        $isOwner = match ($source) {
            'rider' => $order->source === 'rider'
                && (int) $order->requester_id === (int) $actor->id,
            'concierge' => $order->source === 'concierge'
                && (int) $order->concierge_id === (int) $actor->id,
            default => false,
        };

        abort_unless($isOwner, 404);
    }
}
