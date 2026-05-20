<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Review extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'status',
        'is_verified_purchase',
        'helpful_count',
        'not_helpful_count',
        'images',
        'is_featured',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_featured' => 'boolean',
        'images' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified_purchase', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeTopRated(Builder $query): Builder
    {
        return $query->where('rating', '>=', 4);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    public function helpfulScore(): int
    {
        return $this->helpful_count - $this->not_helpful_count;
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved']);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }
}