<?php

namespace App\Models;

use App\Models\Concerns\TracksInventory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TracksInventory;

    /**
     * The public, auto-generated UUID is the real primary key now — the
     * integer `id` column was removed by the
     * `2026_08_31_000002_make_products_uuid_primary_key` migration. `uuid`
     * is filled automatically by `HasUuids` on create (and stays unique via the
     * column's unique index / primary key at the database level).
     */
    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Column(s) that receive an auto-generated UUID on create (HasUuids).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Backward-compatible `->id` reader. The `id` column no longer exists,
     * but a lot of code (resources, controllers, seeders) still reads
     * `$product->id` — it now yields the UUID via this accessor, so those
     * call sites keep working unchanged.
     */
    public function getIdAttribute(): string
    {
        return (string) $this->uuid;
    }

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
        'reserved_stock',
        'damaged_stock',
        'incoming_stock',
        'min_stock_alert',
        'max_stock_level',
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
        // The child column is `product_id` (holding a UUID since the products
        // uuid-key migration); Laravel would otherwise derive `product_uuid`
        // from this model's key name.
        return $this->hasMany(ProductVariant::class, 'product_id');
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
        // Explicit foreign key: the default would derive `product_uuid`
        // from this model's key name.
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function stockMovements()
    {
        // Explicit morph keys: the defaults would derive `stockable_uuid`
        // from this model's key name.
        return $this->morphMany(StockMovement::class, 'stockable', 'stockable_type', 'stockable_id');
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
        // Explicit pivot keys: the foreign pivot key would otherwise be
        // derived as `product_uuid` from this model's key name.
        return $this->belongsToMany(Coupon::class, 'coupon_product', 'product_id', 'coupon_id')
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
