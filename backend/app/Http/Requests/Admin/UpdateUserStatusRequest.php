<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'suspended', 'blocked'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
