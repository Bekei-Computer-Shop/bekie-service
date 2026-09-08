<?php

namespace App\Models;

use App\Models\Concerns\RoutesByUuid;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use RoutesByUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'address_id',
        'order_number',
        'subtotal',
        'discount_total',
        'coupon_id',
        'coupon_code',
        'tax_total',
        'shipping_total',
        'grand_total',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_id',
        'status',
        'notes',
        'shipping_status',
        'tracking_number',
        'shipping_provider',
        'customer_snapshot',
        'address_snapshot',
        'metadata',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected $casts = [
        'customer_snapshot' => 'array',
        'address_snapshot' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The coupon applied at checkout, when one still exists. `coupon_code` is
     * the snapshot to trust — this can be null even when a code was used.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public static function booted(): void
    {
        static::updated(function (self $order): void {
            // wasChanged() only trips on the actual transition into
            // 'cancelled', so re-saving an already-cancelled order (e.g. a
            // payment_status tweak) never restocks twice.
            if ($order->wasChanged('status') && $order->status === 'cancelled') {
                $order->restockItems();
            }
        });
    }

    /**
     * Return every line item's quantity to stock. Mirrors
     * OrderItem::deductStock(), which runs on order creation.
     */
    public function restockItems(): void
    {
        $stockService = app(StockService::class);
        $reference = "Order #{$this->order_number} cancelled";

        foreach ($this->items()->with(['product', 'variant'])->get() as $item) {
            if ($item->product?->track_inventory) {
                $stockService->stockIn($item->product, (int) $item->quantity, 'order_cancelled', $reference, ['order_id' => $this->id]);
            }

            if ($item->variant?->track_inventory) {
                $stockService->stockIn($item->variant, (int) $item->quantity, 'order_cancelled', $reference, ['order_id' => $this->id]);
            }
        }
    }
}
