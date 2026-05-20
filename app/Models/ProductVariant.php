<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'price',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'min_stock_alert',
        'track_inventory',
        'in_stock',
        'weight',
        'length',
        'width',
        'height',
        'image',
        'attributes',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}