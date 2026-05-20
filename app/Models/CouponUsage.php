<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'session_id',
        'coupon_code',
        'discount_amount',
        'used_at',
        'metadata',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
