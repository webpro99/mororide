<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLedgerEntry extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'order_id',
        'transaction_id',
        'payment_intent_id',
        'direction',
        'entry_type',
        'amount',
        'points_delta',
        'balance_after',
        'points_after',
        'reason',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];
}
