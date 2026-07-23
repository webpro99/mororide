<?php

namespace App\Http\Requests\Orders;

class ChooseDriverRequest extends ParticipantOrderRequest
{
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
