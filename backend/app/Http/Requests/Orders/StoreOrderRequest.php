<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['required', 'exists:cities,id'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'pax' => ['nullable', 'integer', 'min:1', 'max:50'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:50'],
            'pickup_name' => ['nullable', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_lat' => ['nullable', 'numeric'],
            'pickup_lng' => ['nullable', 'numeric'],
            'dropoff_name' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['required', 'string', 'max:255'],
            'dropoff_lat' => ['nullable', 'numeric'],
            'dropoff_lng' => ['nullable', 'numeric'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'eta_min' => ['required', 'integer', 'min:1'],
            'offered_fare' => ['nullable', 'numeric', 'min:1'],
            'payment_method' => ['nullable', 'in:cash,card'],
            'vehicle_type' => ['nullable', 'in:sedan,minivan,suv,minibus,luxury'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
