<?php

namespace App\Http\Requests\Admin;

use App\Models\DriverDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(DriverDocument::TYPES)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
