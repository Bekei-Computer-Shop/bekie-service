<?php

namespace App\Models;

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
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}