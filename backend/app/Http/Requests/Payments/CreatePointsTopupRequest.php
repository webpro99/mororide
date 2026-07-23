<?php

namespace App\Http\Requests\Payments;

use App\Services\PaymentConfigurationService;
use Illuminate\Foundation\Http\FormRequest;

class CreatePointsTopupRequest extends FormRequest
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
        $configuration = app(PaymentConfigurationService::class);

        return [
            'amount' => [
                'required',
                'numeric',
                'min:'.$configuration->pointsMinTopup(),
                'max:'.$configuration->pointsMaxTopup(),
                'decimal:0,2',
            ],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
