<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConciergeProfile extends Model
{
    protected $fillable = ['user_id', 'hotel_name', 'hotel_ice', 'hotel_address', 'hotel_website'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
