<?php

namespace App\Models;

use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'sale_price',
        'cost_price',
        'subtotal',
        'discount',
        'tax',
        'total',
        'product_name',
        'product_sku',
        'variant_name',
        'variant_attributes',
        'quantity_shipped',
        'quantity_refunded',
        'status',
        'metadata',
    ];

    protected $casts = [
        'variant_attributes' => 'array',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        // Explicit owner key: Product's primary key is `uuid`, not `id`, so
        // the default belongsTo() would derive a nonexistent `product_uuid`
        // foreign key and this relation would silently always resolve null.
        return $this->belongsTo(Product::class, 'product_id', 'uuid');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public static function booted(): void
    {
        static::created(function (self $item): void {
            $item->deductStock();
        });
    }

    /**
     * Cut stock for whichever of product/variant this line item sold and
     * tracks inventory. Runs for every order-creation path (client checkout,
     * admin manual order) since both create OrderItem rows. Mirrored by
     * Order::restockItems() when the order is cancelled.
     */
    public function deductStock(): void
    {
        $stockService = app(StockService::class);
        $reference = "Order #{$this->order->order_number}";

        if ($this->product?->track_inventory) {
            $stockService->stockOut($this->product, (int) $this->quantity, 'order', $reference, ['order_id' => $this->order_id]);
        }

        if ($this->variant?->track_inventory) {
            $stockService->stockOut($this->variant, (int) $this->quantity, 'order', $reference, ['order_id' => $this->order_id]);
        }
    }
}
