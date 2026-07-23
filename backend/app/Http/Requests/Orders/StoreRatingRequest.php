<?php

namespace App\Http\Requests\Orders;

class StoreRatingRequest extends ParticipantOrderRequest
{
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
