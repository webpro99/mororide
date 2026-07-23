<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason' => ['required', 'in:duplicate,fraudulent,requested_by_customer'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
