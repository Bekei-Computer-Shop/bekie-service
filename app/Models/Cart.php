<?php

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'session_id',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'coupon_code',
        'status',
        'ip_address',
        'user_agent',
        'expires_at',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
