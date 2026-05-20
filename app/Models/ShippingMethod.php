<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ShippingMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_price',
        'price_per_kg',
        'min_weight',
        'max_weight',
        'min_delivery_days',
        'max_delivery_days',
        'is_active',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'min_delivery_days' => 'integer',
        'max_delivery_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->base_price, 2);
    }

    public function getEstimatedDeliveryAttribute(): string
    {
        if ($this->min_delivery_days && $this->max_delivery_days) {
            return "{$this->min_delivery_days}-{$this->max_delivery_days} days";
        }

        return 'N/A';
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    /**
     * Check if shipping method supports a given weight
     */
    public function supportsWeight(float $weight): bool
    {
        if ($this->min_weight && $weight < $this->min_weight) {
            return false;
        }

        if ($this->max_weight && $weight > $this->max_weight) {
            return false;
        }

        return true;
    }

    /**
     * Calculate shipping cost based on weight
     */
    public function calculateCost(float $weight): float
    {
        $cost = $this->base_price;

        if ($this->price_per_kg) {
            $cost += $this->price_per_kg * $weight;
        }

        return round($cost, 2);
    }
}