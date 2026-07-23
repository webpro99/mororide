<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FareConfig extends Model
{
    protected $fillable = [
        'base',
        'per_km',
        'per_min',
        'per_pax',
        'floor',
        'sedan_multiplier',
        'minivan_multiplier',
        'suv_multiplier',
        'minibus_multiplier',
        'luxury_multiplier',
        'platform_fee_pct',
        'currency',
        'is_active',
        'active_from',
        'active_to',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'active_from' => 'datetime',
        'active_to' => 'datetime',
    ];
}
