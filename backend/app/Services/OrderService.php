<?php

namespace App\Services;

use App\Events\OrderAssigned;
use App\Events\OrderDispatchAvailable;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private FareService $fareService,
        private WalletService $walletService,
        private AuditLogService $auditLogService,
        private PaymentService $paymentService,
        private BillingModeService $billingModeService,
        private NotificationService $notificationService
    ) {}

    public function createRiderOrder(User $rider, array $data): Order
    {
        return $this->createOrder($rider, null, 'rider', $data);
    }

    public function createConciergeOrder(User $concierge, array $data): Order
    {
        return $this->createOrder($concierge, $concierge, 'concierge', $data);
    }

    public function assignDriver(Order $order, User $driver, ?float $finalFare = null): Order
    {
        if (! in_array($order->status, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)) {
            throw new RuntimeException('Order is not available for assignment.');
        }

        $assignedOrder = DB::transaction(function () use ($order, $driver, $finalFare) {
            $oldStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_ASSIGNED,
                'assigned_driver_id' => $driver->id,
                'final_fare' => $finalFare ?: $order->offered_fare,
                'assigned_at' => now(),
            ]);
            $this->recordStatus($order, $driver, $oldStatus, Order::STATUS_ASSIGNED, 'Driver assigned.');
            $this->auditLogService->record($driver, 'driver_assigned_order', $order);

            return $order->fresh(['driver', 'city', 'offers']);
        });

        OrderAssigned::dispatch($assignedOrder);

        $recipient = $assignedOrder->source === 'concierge'
            ? $assignedOrder->concierge
            : $assignedOrder->requester;
        if ($recipient !== null) {
            $this->notificationService->push(
                $recipient,
                'driver_assigned',
                'Your driver accepted the ride',
                "{$driver->name} accepted ride #{$assignedOrder->id}. You can now open the chat.",
                [
                    'order_id' => $assignedOrder->id,
                    'driver_id' => $driver->id,
                    'status' => Order::STATUS_ASSIGNED,
                    'screen' => 'chat',
                ]
            );
        }

        return $assignedOrder;
    }

    public function markArrived(Order $order, User $driver): Order
    {
        $this->ensureAssignedDriver($order, $driver);

        return $this->transition($order, $driver, Order::STATUS_ASSIGNED, Order::STATUS_ARRIVED, ['arrived_at' => now()]);
    }

    public function startRide(Order $order, User $driver): Order
    {
        $this->ensureAssignedDriver($order, $driver);

        return $this->transition($order, $driver, Order::STATUS_ARRIVED, Order::STATUS_IN_PROGRESS, ['started_at' => now()]);
    }

    public function completeRide(Order $order, User $driver): Order
    {
        $this->ensureAssignedDriver($order, $driver);

        if ($order->status !== Order::STATUS_IN_PROGRESS) {
            throw new RuntimeException('Only in-progress rides can be completed.');
        }

        $fare = (float) ($order->final_fare ?: $order->offered_fare);
        $freeLaunch = $this->billingModeService->freeLaunchEnabled();

        try {
            return DB::transaction(function () use ($order, $driver, $fare, $freeLaunch) {
                $cardPayment = null;
                if ($order->payment_method === 'card') {
                    // Lock the payment row in the same transaction as ride
                    // settlement so a concurrent refund/dispute cannot race it.
                    $cardPayment = $this->paymentService->hasSucceededRidePayment($order, $fare, true);

                    if (! $cardPayment) {
                        throw new RuntimeException('The card payment for the current fare has not succeeded yet.');
                    }
                }

                $fee = $freeLaunch ? 0 : $this->fareService->calculatePlatformFee($fare);
                $net = round($fare - $fee, 2);

                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'final_fare' => $fare,
                ]);
                $this->recordStatus($order, $driver, Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED, 'Ride completed.');

                $transaction = Transaction::create([
                    'order_id' => $order->id,
                    'type' => $order->payment_method,
                    'source' => $order->source,
                    'rider_id' => $order->source === 'rider' ? $order->requester_id : null,
                    'concierge_id' => $order->concierge_id,
                    'driver_id' => $driver->id,
                    'city_id' => $order->city_id,
                    'fare' => $fare,
                    'fee' => $fee,
                    'net' => $net,
                    'currency' => $this->transactionCurrency(),
                    'status' => 'succeeded',
                    'payment_provider_references' => $cardPayment ? [
                        'provider' => 'stripe',
                        'payment_intent_id' => $cardPayment->provider_intent_id,
                    ] : null,
                    'metadata' => [
                        'mode' => $freeLaunch ? 'free_launch' : ($order->payment_method === 'card' ? 'stripe' : 'cash_mvp'),
                        'billing_off' => $freeLaunch,
                    ],
                ]);

                if ($order->payment_method === 'card') {
                    $this->walletService->creditCardEarning($driver, $order, $transaction, $net);
                } elseif ($freeLaunch) {
                    $this->walletService->waiveCashCommission($driver, $order, $transaction, 'Free launch mode: platform commission waived.');
                } else {
                    $this->walletService->debitCashCommission($driver, $order, $transaction, $fee);
                }

                $this->auditLogService->record($driver, 'ride_completed', $order);

                return $order->fresh(['transaction', 'driver.wallet', 'city']);
            });
        } catch (RuntimeException $exception) {
            if (WalletService::isInsufficientCashCommission($exception)) {
                // The completion transaction has already rolled back at this
                // point, so this safety block is persisted independently.
                $this->walletService->blockDriverIfNeeded($driver);
                $order->refresh();
            }

            throw $exception;
        }
    }

    public function cancelRide(Order $order, User $actor): Order
    {
        return $this->transition($order, $actor, $order->status, Order::STATUS_CANCELLED, ['cancelled_at' => now()]);
    }

    /**
     * Expire orders that are still open (searching/offered) past their
     * expires_at. Each expiry is locked and re-checked so a driver that just
     * accepted the ride is never overwritten. Returns the number expired.
     */
    public function expireStaleOrders(?int $limit = 200): int
    {
        $ids = Order::query()
            ->whereIn('status', [Order::STATUS_SEARCHING, Order::STATUS_OFFERED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            $didExpire = DB::transaction(function () use ($id) {
                $order = Order::lockForUpdate()->find($id);

                if (! $order
                    || ! in_array($order->status, [Order::STATUS_SEARCHING, Order::STATUS_OFFERED], true)
                    || $order->expires_at === null
                    || $order->expires_at->isFuture()) {
                    return false;
                }

                $from = $order->status;
                $order->update(['status' => Order::STATUS_EXPIRED]);
                $order->offers()
                    ->where('status', 'pending')
                    ->update(['status' => 'expired']);
                $this->recordStatus($order, null, $from, Order::STATUS_EXPIRED, 'Order expired automatically.');

                return true;
            });

            if ($didExpire) {
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Admin-initiated cancellation with an audit trail and reason.
     */
    public function cancelByAdmin(User $admin, Order $order, string $reason): Order
    {
        if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            throw new RuntimeException('Completed or already cancelled orders cannot be cancelled.');
        }

        return DB::transaction(function () use ($admin, $order, $reason) {
            $old = $order->status;

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
                'cancel_reason' => $reason,
            ]);

            $this->recordStatus($order, $admin, $old, Order::STATUS_CANCELLED, 'Cancelled by admin: '.$reason);
            $this->auditLogService->record($admin, 'ride_cancelled', $order, ['status' => $old], ['status' => Order::STATUS_CANCELLED, 'reason' => $reason]);

            return $order->fresh(['city', 'driver', 'requester']);
        });
    }

    /**
     * Flag an order for review (e.g. suspicious activity, dispute).
     */
    public function flagOrder(User $admin, Order $order, string $reason): Order
    {
        $order->update([
            'flagged_at' => now(),
            'flag_reason' => $reason,
            'flagged_by' => $admin->id,
        ]);

        $this->auditLogService->record($admin, 'order_flagged', $order, [], ['reason' => $reason]);

        return $order->fresh(['city', 'driver', 'requester']);
    }

    private function createOrder(User $requester, ?User $concierge, string $source, array $data): Order
    {
        $order = DB::transaction(function () use ($requester, $concierge, $source, $data) {
            try {
                $estimate = $this->fareService->estimateFare(
                    (float) $data['distance_km'],
                    (int) $data['eta_min'],
                    (int) ($data['pax'] ?? 1),
                    $data['vehicle_type'] ?? 'sedan'
                );
            } catch (RuntimeException $exception) {
                $estimate = null;
            }

            if (! isset($data['offered_fare']) && ! $estimate) {
                throw new RuntimeException('Enter an offered fare or activate fare pricing.');
            }

            $offeredFare = (float) ($data['offered_fare'] ?? $estimate['suggested_fare']);

            $order = Order::create([
                'source' => $source,
                'requester_id' => $requester->id,
                'concierge_id' => $concierge?->id,
                'city_id' => $data['city_id'],
                'hotel_name' => $data['hotel_name'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'languages' => $data['languages'] ?? null,
                'pax' => $data['pax'] ?? 1,
                'luggage' => $data['luggage'] ?? 0,
                'pickup_name' => $data['pickup_name'] ?? null,
                'pickup_address' => $data['pickup_address'],
                'pickup_lat' => $data['pickup_lat'] ?? null,
                'pickup_lng' => $data['pickup_lng'] ?? null,
                'dropoff_name' => $data['dropoff_name'] ?? null,
                'dropoff_address' => $data['dropoff_address'],
                'dropoff_lat' => $data['dropoff_lat'] ?? null,
                'dropoff_lng' => $data['dropoff_lng'] ?? null,
                'distance_km' => $data['distance_km'],
                'eta_min' => $data['eta_min'],
                'offered_fare' => $offeredFare,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => Order::STATUS_SEARCHING,
                'note' => $data['note'] ?? null,
                'expires_at' => now()->addMinutes(10),
            ]);

            $this->recordStatus($order, $requester, null, Order::STATUS_SEARCHING, 'Order created.');
            $this->auditLogService->record($requester, 'order_created', $order);

            return $order->fresh(['city', 'requester']);
        });

        OrderDispatchAvailable::dispatch($order);

        return $order;
    }

    private function transition(Order $order, User $actor, string $expected, string $next, array $attributes): Order
    {
        if ($order->status !== $expected) {
            throw new RuntimeException("Order must be {$expected} before {$next}.");
        }

        $order->update(array_merge($attributes, ['status' => $next]));
        $this->recordStatus($order, $actor, $expected, $next, "Order moved to {$next}.");

        return $order->fresh(['driver', 'city']);
    }

    private function transactionCurrency(): string
    {
        try {
            return $this->fareService->getActiveConfig()->currency;
        } catch (RuntimeException) {
            return 'MAD';
        }
    }

    private function ensureAssignedDriver(Order $order, User $driver): void
    {
        if ((int) $order->assigned_driver_id !== (int) $driver->id) {
            throw new RuntimeException('Only the assigned driver can perform this action.');
        }
    }

    private function recordStatus(Order $order, ?User $actor, ?string $from, string $to, string $note): void
    {
        OrderStatusEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);

        OrderStatusChanged::dispatch($order, $actor?->id, $from, $to);
    }
}
