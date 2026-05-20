<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WishlistItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'wishlist_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
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
