<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FlagOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
