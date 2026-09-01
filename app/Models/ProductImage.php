<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

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

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'sort_order' => 'integer',
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

    /**
     * A URL the browser can actually load.
     *
     * Uploads go through MediaController to Cloudinary, which hands back an
     * absolute secure_url — that is what `image` normally holds, and running it
     * through Storage::url() would mangle it into a doubled path. Only a
     * relative path is resolved against the disk.
     */
    public function getUrlAttribute(): string
    {
        $image = (string) $this->image;

        if ($image === '' || preg_match('#^(https?://|data:|//)#i', $image) === 1) {
            return $image;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk ?: 'public');

        return $disk->url($image);
    }
}
