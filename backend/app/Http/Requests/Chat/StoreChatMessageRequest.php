<?php

namespace App\Http\Requests\Chat;

use App\Models\Order;
use App\Services\ParticipantChatService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        abort_unless(
            $order instanceof Order
            && app(ParticipantChatService::class)->canParticipate($order, $this->user()),
            404
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:2000', 'required_without:image_url'],
            'image_url' => ['nullable', 'string', 'max:255', 'url:http,https', 'required_without:text'],
        ];
    }
}
