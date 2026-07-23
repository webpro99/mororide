<?php

namespace App\Http\Requests\Orders;

class CancelParticipantOrderRequest extends ParticipantOrderRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
