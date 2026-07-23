<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFareConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'base' => ['required', 'numeric', 'min:0'],
            'per_km' => ['required', 'numeric', 'min:0'],
            'per_min' => ['required', 'numeric', 'min:0'],
            'per_pax' => ['required', 'numeric', 'min:0'],
            'floor' => ['required', 'numeric', 'min:0'],
            'sedan_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'minivan_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'suv_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'minibus_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'luxury_multiplier' => ['nullable', 'numeric', 'min:0.1'],
            'platform_fee_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
