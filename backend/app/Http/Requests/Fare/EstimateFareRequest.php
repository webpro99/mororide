<?php

namespace App\Http\Requests\Fare;

use Illuminate\Foundation\Http\FormRequest;

class EstimateFareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'eta_min' => ['required', 'integer', 'min:1'],
            'pax' => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehicle_type' => ['nullable', 'in:sedan,minivan,suv,minibus,luxury'],
        ];
    }
}
