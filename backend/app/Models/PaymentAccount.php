<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'country',
        'status',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'capabilities',
        'requirements',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'capabilities' => 'array',
        'requirements' => 'array',
        'onboarding_completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
