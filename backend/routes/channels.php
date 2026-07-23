<?php

use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

$isOrderParticipant = function (User $user, int|string $orderId): bool {
    if ($user->status !== 'active') {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    return match ($user->role) {
        'rider' => Order::query()
            ->whereKey($orderId)
            ->where('source', 'rider')
            ->where('requester_id', $user->id)
            ->exists(),
        'concierge' => Order::query()
            ->whereKey($orderId)
            ->where('source', 'concierge')
            ->where('concierge_id', $user->id)
            ->exists(),
        'driver' => Order::query()
            ->whereKey($orderId)
            ->where('assigned_driver_id', $user->id)
            ->exists(),
        default => false,
    };
};

$isChatParticipant = function (User $user, int|string $orderId) use ($isOrderParticipant): bool {
    if (! $isOrderParticipant($user, $orderId)) {
        return false;
    }

    return $user->role === 'admin'
        || Order::query()->whereKey($orderId)->whereNotNull('assigned_driver_id')->exists();
};

Broadcast::channel('drivers.{city_id}', function (User $user, int|string $cityId): bool {
    $profile = $user->driverProfile;

    if ($user->role !== 'driver'
        || $user->status !== 'active'
        || ! $profile
        || $profile->approval_state !== 'approved'
        || ! $profile->online_status
        || $profile->blocked_at !== null) {
        return false;
    }

    $latestCityId = DriverLocation::query()
        ->where('driver_id', $user->id)
        ->latest('reported_at')
        ->latest('id')
        ->value('city_id');

    return $latestCityId !== null && (int) $latestCityId === (int) $cityId;
});

Broadcast::channel('order.{order_id}', $isOrderParticipant);

Broadcast::channel('driver.{driver_id}.location', function (User $user, int|string $driverId): bool {
    if ($user->status !== 'active') {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    if ($user->role === 'driver' && (int) $user->id === (int) $driverId) {
        return true;
    }

    $activeRide = Order::query()
        ->where('assigned_driver_id', $driverId)
        ->whereIn('status', [
            Order::STATUS_ASSIGNED,
            Order::STATUS_ARRIVED,
            Order::STATUS_IN_PROGRESS,
        ]);

    return match ($user->role) {
        'rider' => $activeRide
            ->where('source', 'rider')
            ->where('requester_id', $user->id)
            ->exists(),
        'concierge' => $activeRide
            ->where('source', 'concierge')
            ->where('concierge_id', $user->id)
            ->exists(),
        default => false,
    };
});

Broadcast::channel('chat.{order_id}', $isChatParticipant);

Broadcast::channel('admin.feed', function (User $user): bool {
    return $user->role === 'admin' && $user->status === 'active';
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return $user->status === 'active' && (int) $user->id === (int) $id;
});
