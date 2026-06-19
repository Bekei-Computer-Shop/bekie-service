<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'sale_price',
        'cost_price',
        'subtotal',
        'discount',
        'total',
        'product_name',
        'product_sku',
        'variant_name',
        'variant_attributes',
        'is_available',
        'metadata',
    ];

    protected $casts = [
        'variant_attributes' => 'array',
        'metadata' => 'array',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
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
