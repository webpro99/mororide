<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_SEARCHING = 'searching';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'source',
        'requester_id',
        'concierge_id',
        'city_id',
        'hotel_name',
        'guest_name',
        'languages',
        'pax',
        'luggage',
        'pickup_name',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_name',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'eta_min',
        'offered_fare',
        'final_fare',
        'payment_method',
        'status',
        'assigned_driver_id',
        'note',
        'expires_at',
        'assigned_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'flagged_at',
        'flag_reason',
        'flagged_by',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'languages' => 'array',
        'expires_at' => 'datetime',
        'assigned_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'flagged_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function concierge()
    {
        return $this->belongsTo(User::class, 'concierge_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function offers()
    {
        return $this->hasMany(OrderOffer::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function paymentIntents()
    {
        return $this->hasMany(PaymentIntent::class);
    }
}
