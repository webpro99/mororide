<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Order;
use App\Services\ParticipantChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends ApiController
{
    public function index(Request $request, Order $order, ParticipantChatService $chatService)
    {
        $messages = $chatService->messages($order, $request->user());

        return $this->ok([
            'order_id' => $order->id,
            'messages' => ChatMessageResource::collection($messages->getCollection()),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function store(
        StoreChatMessageRequest $request,
        Order $order,
        ParticipantChatService $chatService
    ) {
        $message = $chatService->send($order, $request->user(), $request->validated());

        return $this->ok(new ChatMessageResource($message), 'Message sent', 201);
    }

    /**
     * Upload an image attachment for an in-ride chat. The participant check runs
     * before any payload validation so a non-participant receives 404 and never
     * learns whether the order exists.
     */
    public function storeImage(Request $request, Order $order, ParticipantChatService $chatService)
    {
        abort_unless($chatService->canParticipate($order, $request->user()), 404);

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'text' => ['nullable', 'string', 'max:2000'],
        ]);

        $path = $request->file('image')->store("chat/{$order->id}", 'public');
        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, '/')) {
            $url = rtrim((string) config('app.url'), '/').$url;
        }

        $message = $chatService->send($order, $request->user(), [
            'text' => $validated['text'] ?? null,
            'image_url' => $url,
        ]);

        return $this->ok(new ChatMessageResource($message), 'Message sent', 201);
    }
}
