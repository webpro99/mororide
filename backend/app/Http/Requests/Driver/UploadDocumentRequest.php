<?php

namespace App\Http\Requests\Driver;

use App\Models\DriverDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('driver') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(DriverDocument::TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
