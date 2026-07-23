<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderOffer extends Model
{
    protected $fillable = ['order_id', 'driver_id', 'type', 'amount', 'message', 'status'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
