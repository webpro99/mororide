<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;

    public int $orderId;

    public int $senderId;

    public string $senderRole;

    public ?string $text;

    public ?string $imageUrl;

    public string $sentAt;

    public function __construct(ChatMessage $message)
    {
        $this->messageId = (int) $message->id;
        $this->orderId = (int) $message->order_id;
        $this->senderId = (int) $message->sender_id;
        $this->senderRole = $message->sender_role;
        $this->text = $message->text;
        $this->imageUrl = $message->image_url;
        $this->sentAt = ($message->created_at ?? now())->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->orderId}"),
            new PrivateChannel('admin.feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'order_id' => $this->orderId,
            'sender_id' => $this->senderId,
            'sender_role' => $this->senderRole,
            'text' => $this->text,
            'image_url' => $this->imageUrl,
            'sent_at' => $this->sentAt,
        ];
    }
}
