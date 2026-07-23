<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        // This endpoint intentionally also serves public/global notifications,
        // so it is outside the required auth middleware. Resolve Sanctum
        // explicitly to personalize the response when a bearer token exists.
        $user = Auth::guard('sanctum')->user();

        $query = Notification::query()
            ->when($user, function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner
                        ->whereNull('user_id')->whereNull('role')
                        ->orWhere('user_id', $user->id)
                        ->orWhere(function ($roleQuery) use ($user) {
                            $roleQuery
                                ->whereNull('user_id')
                                ->where('role', $user->role);
                        });
                });
            }, function ($query) {
                $query->whereNull('user_id')->whereNull('role');
            });

        if ($user) {
            $query->with(['reads' => fn ($reads) => $reads->where('user_id', $user->id)]);
        }

        $notifications = $query
            ->latest()
            ->limit(20)
            ->get();

        if ($user) {
            $notifications->each(function (Notification $notification) {
                if ($notification->user_id === null) {
                    $notification->setAttribute('read_at', $notification->reads->first()?->read_at);
                }

                $notification->unsetRelation('reads');
            });
        }

        return $this->ok($notifications);
    }

    public function markRead(Request $request, Notification $notification)
    {
        $user = $request->user();
        $isOwned = $notification->user_id !== null
            && (int) $notification->user_id === (int) $user->id;
        $isGlobal = $notification->user_id === null && $notification->role === null;
        $isForRole = $notification->user_id === null && $notification->role === $user->role;

        abort_unless($isOwned || $isGlobal || $isForRole, 404);

        if ($notification->user_id === null) {
            $read = NotificationRead::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ],
                ['read_at' => now()]
            );

            $notification->setAttribute('read_at', $read->read_at);
        } else {
            $notification->update(['read_at' => now()]);
        }

        return $this->ok($notification, 'Notification marked as read');
    }
}
