<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDocumentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'requested_by',
        'note',
        'fulfilled_at',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
