<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_name',
        'vehicle_plate',
        'vehicle_type',
        'tourism_license_no',
        'approval_state',
        'online_status',
        'current_lat',
        'current_lng',
        'payment_account_id',
        'payout_enabled',
        'blocked_at',
    ];

    protected $casts = [
        'online_status' => 'boolean',
        'payout_enabled' => 'boolean',
        'current_lat' => 'float',
        'current_lng' => 'float',
        'blocked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
