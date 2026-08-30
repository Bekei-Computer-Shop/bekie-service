<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
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
        'reorder_point',
        'track_inventory',
        'in_stock',
        'warehouse_location',
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
        'version',
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

    /**
     * The product's image gallery, in the order the storefront should show it.
     *
     * Ordered on the relation rather than at the call site so every eager load
     * (`with('images')`, `load('images')`) gets the same sequence — the primary
     * image is not guaranteed to be sort_order 0, so it is not sorted first
     * here; consumers pick it out via `is_primary`.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'stockable');
    }

    /**
     * Promotions applied to this product.
     *
     * Backed by `Coupon`, not `Promotion`: the admin promotions API
     * (PromotionController) reads and writes coupons, so those are the rows the
     * product form offers. Soft-deleted coupons are excluded so a deleted
     * promotion stops applying without needing the pivot row cleaned up.
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_product')
            ->whereNull('coupons.deleted_at');
    }

    public static function booted(): void
    {
        static::creating(function (self $product): void {
            if (! $product->uuid) {
                $product->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $product) {
            $product->version++;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_alert');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where(function ($q) {
            $q->where('stock_quantity', '<=', 0)
              ->orWhere('in_stock', false);
        });
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function getStockValueAttribute()
    {
        return (int) $this->stock_quantity * (float) $this->cost_price;
    }
}
