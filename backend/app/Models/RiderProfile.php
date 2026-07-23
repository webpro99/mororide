<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = ['user_id', 'preferred_language'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
