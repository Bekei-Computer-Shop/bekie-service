<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'short_description',
        'description',
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
        'thumbnail',
        'meta_title',
        'meta_description',
        'is_active',
        'is_featured',
        'is_digital',
        'views_count',
        'sales_count',
        'sort_order',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
