<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'provider_event_id',
        'type',
        'livemode',
        'payload_hash',
        'status',
        'attempts',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'livemode' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
