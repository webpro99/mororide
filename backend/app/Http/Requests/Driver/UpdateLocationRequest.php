<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public const MAX_REPORT_AGE_MINUTES = 5;

    public const MAX_FUTURE_CLOCK_SKEW_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'reported_at' => [
                'required',
                Rule::date()
                    ->afterOrEqual(now()->subMinutes(self::MAX_REPORT_AGE_MINUTES)->toDateTimeString())
                    ->beforeOrEqual(now()->addSeconds(self::MAX_FUTURE_CLOCK_SKEW_SECONDS)->toDateTimeString()),
            ],
        ];
    }
}
