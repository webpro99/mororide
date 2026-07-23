<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'points_delta' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'balance_delta' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('points_delta') && ! $this->filled('balance_delta')) {
                $validator->errors()->add('points_delta', 'Provide a points_delta and/or a balance_delta.');
            }
        });
    }
}
