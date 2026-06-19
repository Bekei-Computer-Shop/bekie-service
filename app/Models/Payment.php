<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_reference',
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'currency_rate',
        'status',
        'payment_method',
        'card_brand',
        'last4',
        'gateway_response',
        'metadata',
        'refunded_amount',
        'refunded_at',
        'authorized_at',
        'paid_at',
        'failed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'metadata' => 'array',
        'authorized_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
