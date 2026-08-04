<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'image',
        'disk',
        'mime_type',
        'file_size',
        'alt_text',
        'title',
        'type',
        'is_primary',
        'is_active',
        'sort_order',
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

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->image);
    }
}
