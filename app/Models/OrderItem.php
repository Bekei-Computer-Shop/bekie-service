<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class, 'order_id', 'order_id')
            ->where('product_id', $this->product_id)
            ->when($this->product_variant_id, fn ($q) => $q->where('product_variant_id', $this->product_variant_id));
    }
}
