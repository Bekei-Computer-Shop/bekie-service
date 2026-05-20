<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'payment_id',
        'order_id',
        'user_id',
        'transaction_reference',
        'type',
        'amount',
        'currency',
        'direction',
        'status',
        'balance_before',
        'balance_after',
        'provider',
        'provider_transaction_id',
        'metadata',
        'description',
        'ip_address',
        'user_agent',
        'processed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}