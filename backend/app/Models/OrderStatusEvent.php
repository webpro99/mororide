<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusEvent extends Model
{
    protected $fillable = ['order_id', 'actor_id', 'from_status', 'to_status', 'note'];
}
