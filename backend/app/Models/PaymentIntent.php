<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    public const PURPOSE_RIDE = 'ride';

    public const PURPOSE_POINTS_TOPUP = 'points_topup';

    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'provider_intent_id',
        'purpose',
        'amount',
        'amount_minor',
        'currency',
        'status',
        'idempotency_key',
        'failure_code',
        'failure_message',
        'provider_metadata',
        'succeeded_at',
        'refunded_at',
    ];

    protected $hidden = ['idempotency_key'];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'provider_metadata' => 'array',
        'succeeded_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
