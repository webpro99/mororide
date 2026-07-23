<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'status',
        'password',
        'suspended_at',
        'status_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function riderProfile()
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function driverDocuments()
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'driver_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function driverLocations()
    {
        return $this->hasMany(DriverLocation::class, 'driver_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function conciergeProfile()
    {
        return $this->hasOne(ConciergeProfile::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function paymentAccount()
    {
        return $this->hasOne(PaymentAccount::class);
    }

    public function paymentIntents()
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function isRole(string $role): bool
    {
        return $this->role === $role;
    }
}
