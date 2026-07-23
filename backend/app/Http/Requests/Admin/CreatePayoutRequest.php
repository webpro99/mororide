<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'driver')],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'method' => ['nullable', Rule::in(['manual', 'bank', 'stripe'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
