<?php

namespace App\Services;

use App\Events\PushNotificationRequested;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Persist an in-app notification targeted at a single user, and request a
     * push delivery to their registered devices (best-effort, after commit).
     */
    public function push(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'role' => null,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        PushNotificationRequested::dispatch($user->id, $title, $body, array_merge($data, ['type' => $type]));

        return $notification;
    }

    /**
     * Persist an in-app notification broadcast to every user of a role.
     */
    public function pushToRole(string $role, string $type, string $title, string $body, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => null,
            'role' => $role,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
