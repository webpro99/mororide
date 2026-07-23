<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ParticipantChatService
{
    public function __construct(private NotificationService $notificationService) {}

    public function messages(Order $order, User $user): LengthAwarePaginator
    {
        $this->ensureParticipant($order, $user);

        return $order->messages()
            ->with('sender')
            ->oldest('created_at')
            ->oldest('id')
            ->paginate(50);
    }

    public function send(Order $order, User $user, array $data): ChatMessage
    {
        $this->ensureParticipant($order, $user);

        $message = $order->messages()->create([
            'sender_id' => $user->id,
            'sender_role' => $user->role,
            'text' => $data['text'] ?? null,
            'image_url' => $data['image_url'] ?? null,
        ]);

        $message->load('sender');
        ChatMessageSent::dispatch($message);

        $recipient = $user->role === 'driver'
            ? User::find($order->source === 'concierge' ? $order->concierge_id : $order->requester_id)
            : User::find($order->assigned_driver_id);

        if ($recipient && (int) $recipient->id !== (int) $user->id) {
            $preview = $message->text
                ? Str::limit($message->text, 90)
                : 'Sent you a photo.';
            $this->notificationService->push(
                $recipient,
                'chat_message',
                "New message from {$user->name}",
                $preview,
                [
                    'order_id' => $order->id,
                    'message_id' => $message->id,
                    'sender_role' => $user->role,
                    'screen' => 'messages',
                ]
            );
        }

        return $message;
    }

    public function canParticipate(Order $order, ?User $user): bool
    {
        if ($user === null || $order->assigned_driver_id === null) {
            return false;
        }

        $isRequester = match ($user->role) {
            'rider' => $order->source === 'rider'
                && (int) $order->requester_id === (int) $user->id,
            'concierge' => $order->source === 'concierge'
                && (int) $order->concierge_id === (int) $user->id,
            default => false,
        };

        $isAssignedDriver = $user->role === 'driver'
            && (int) $order->assigned_driver_id === (int) $user->id;

        return $isRequester || $isAssignedDriver;
    }

    private function ensureParticipant(Order $order, User $user): void
    {
        abort_unless($this->canParticipate($order, $user), 404);
    }
}
