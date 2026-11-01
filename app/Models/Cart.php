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

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * Empty the cart once its contents have become an order.
     *
     * A user keeps one cart (looked up by user_id and a null session_id), so it
     * is emptied in place rather than replaced. The order holds its own copy of
     * every line, so nothing is lost. Call inside the order transaction, so a
     * rolled-back order leaves the cart as it was.
     */
    public function clearAfterCheckout(): void
    {
        // Soft-deleted, like a line the customer removes themselves.
        $this->items()->delete();

        $this->forceFill([
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
            'coupon_code' => null,
            'status' => 'active',
            'last_activity_at' => now(),
        ])->save();

        $this->unsetRelation('items');
    }
}
