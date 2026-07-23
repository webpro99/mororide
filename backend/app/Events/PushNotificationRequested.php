<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an in-app notification for a single user is persisted. Dispatched
 * only after the surrounding DB transaction commits, so the external push call
 * never runs inside a transaction.
 */
class PushNotificationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
