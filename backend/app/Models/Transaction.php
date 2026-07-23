<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'source',
        'rider_id',
        'concierge_id',
        'driver_id',
        'city_id',
        'fare',
        'fee',
        'net',
        'currency',
        'status',
        'payment_provider_references',
        'metadata',
    ];

    protected $casts = [
        'payment_provider_references' => 'array',
        'metadata' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
