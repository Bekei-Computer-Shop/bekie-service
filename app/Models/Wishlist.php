<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Wishlist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'session_id',
        'name',
        'description',
        'is_public',
        'is_active',
        'metadata',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
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

    public function items()
    {
        return $this->hasMany(WishlistItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function isOwnedByUser(): bool
    {
        return !is_null($this->user_id);
    }

    public function isGuestWishlist(): bool
    {
        return is_null($this->user_id) && !is_null($this->session_id);
    }

    public function attachUser(int $userId): void
    {
        $this->update([
            'user_id' => $userId,
            'session_id' => null,
        ]);
    }

    public function addItem(int $productId): void
    {
        $this->items()->firstOrCreate([
            'product_id' => $productId,
        ]);
    }

    public function removeItem(int $productId): void
    {
        $this->items()
            ->where('product_id', $productId)
            ->delete();
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }
}