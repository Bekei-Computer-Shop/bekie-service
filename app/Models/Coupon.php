<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    /**
     * Campaign kinds the admin promotion form offers.
     *
     * Distinct from `type`, which stays ('fixed'|'percentage') and drives
     * calculateDiscount(). A kind says what the campaign IS; the type says how
     * its money is worked out. A null kind is an ordinary discount.
     *
     * A coupon applied by code discounts the subtotal by its type and value.
     * When attached to a product, the same configured discount applies to
     * that product's order lines.
     */
    public const KINDS = [
        'flash_sale',
        'bogo',
        'free_shipping',
        'bundle',
        'free_gift',
    ];

    /**
     * What a promotion targets: exactly one of these. Null is a legacy
     * untargeted coupon applied to the whole order by code.
     */
    public const TARGET_PRODUCTS = 'products';

    public const TARGET_CATEGORIES = 'categories';

    public const TARGET_CUSTOMER_GROUPS = 'customer_groups';

    public const TARGET_TYPES = [
        self::TARGET_PRODUCTS,
        self::TARGET_CATEGORIES,
        self::TARGET_CUSTOMER_GROUPS,
    ];

    /**
     * Request field => relation, per target type. Variants are an optional
     * refinement of a product target, so they belong to it.
     *
     * @var array<string, array<string, string>>
     */
    public const TARGET_FIELDS = [
        self::TARGET_PRODUCTS => ['product_ids' => 'products', 'variant_ids' => 'variants'],
        self::TARGET_CATEGORIES => ['category_ids' => 'categories'],
        self::TARGET_CUSTOMER_GROUPS => ['customer_group_ids' => 'customerGroups'],
    ];

    /** The field that must name at least one target, per target type. */
    public const PRIMARY_TARGET_FIELDS = [
        self::TARGET_PRODUCTS => 'product_ids',
        self::TARGET_CATEGORIES => 'category_ids',
        self::TARGET_CUSTOMER_GROUPS => 'customer_group_ids',
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'kind',
        'target_type',
        'value',
        'usage_limit',
        'used_count',
        'user_limit',
        'starts_at',
        'expires_at',
        'min_order_amount',
        'max_discount_amount',
        'include_subcategories',
        'is_active',
        'applicable_products',
        'applicable_categories',
        'description',
        'banner_image',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',

        'starts_at' => 'datetime',
        'expires_at' => 'datetime',

        'is_active' => 'boolean',
        'include_subcategories' => 'boolean',

        'applicable_products' => 'array',
        'applicable_categories' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    public function canBeUsedByUser(int $userUsageCount): bool
    {
        if ($this->user_limit !== null && $userUsageCount >= $this->user_limit) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if ($orderTotal < ($this->min_order_amount ?? 0)) {
            return 0;
        }

        $discount = $this->type === 'fixed'
            ? $this->value
            : ($orderTotal * ($this->value / 100));

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'coupon_customer_group');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user');
    }

    /**
     * Products this promotion applies to. Inverse of Product::promotions().
     */
    public function products(): BelongsToMany
    {
        // Explicit pivot keys: the related pivot key would otherwise be
        // derived as `product_uuid` from Product's key name.
        return $this->belongsToMany(Product::class, 'coupon_product', 'coupon_id', 'product_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'coupon_product_variant', 'coupon_id', 'product_variant_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
