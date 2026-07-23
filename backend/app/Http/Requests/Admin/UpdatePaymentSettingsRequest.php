<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'connect_country' => strtoupper((string) $this->input('connect_country', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['cash_only', 'stripe'])],
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'publishable_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in(['MAD', 'mad', 'USD', 'usd'])],
            'points_min_topup' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'points_max_topup' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'points_per_currency_unit' => ['nullable', 'required_without:point_price', 'numeric', 'gt:0', 'max:1000000'],
            'point_price' => ['nullable', 'required_without:points_per_currency_unit', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,4'],
            'connect_enabled' => ['required', 'boolean'],
            'connect_country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'connect_refresh_url' => ['nullable', 'url:http,https', 'max:2048'],
            'connect_return_url' => ['nullable', 'url:http,https', 'max:2048'],
            'clear_secrets' => ['sometimes', 'array'],
            'clear_secrets.*' => [Rule::in(['secret_key', 'webhook_secret'])],
        ];
    }
}
