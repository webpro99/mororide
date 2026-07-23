<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The 7 documents every driver must provide before approval.
     */
    public const TYPES = [
        'profile',
        'vehicle_out',
        'vehicle_in',
        'id_front',
        'id_back',
        'license',
        'tourism_agreement',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'reviewed_by',
        'reviewed_at',
        'note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
