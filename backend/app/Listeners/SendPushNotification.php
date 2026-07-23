<?php

namespace App\Listeners;

use App\Events\PushNotificationRequested;
use App\Models\User;
use App\Services\ExpoPushService;

class SendPushNotification
{
    public function __construct(private ExpoPushService $expoPush) {}

    public function handle(PushNotificationRequested $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $this->expoPush->sendToUser($user, $event->title, $event->body, $event->data);
    }
}
