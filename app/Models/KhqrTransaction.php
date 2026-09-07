<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhqrTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'transaction_id',
        'merchant_id',
        'amount',
        'currency',
        'khqr_data',
        'status',
        'paid_at',
        'cancelled_at',
        'expires_at',
        'payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
