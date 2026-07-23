<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    protected $fillable = [
        'driver_id',
        'city_id',
        'lat',
        'lng',
        'reported_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'reported_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
