<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Banner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'subtitle',
        'image_desktop',
        'image_mobile',
        'button_text',
        'button_url',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
        'position',
        'meta',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
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

    public function scopePosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
              ->orWhere('starts_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>=', now());
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function isVisible(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function hasAction(): bool
    {
        return !empty($this->button_url);
    }

    public function getImageUrl(string $device = 'desktop'): ?string
    {
        return $device === 'mobile'
            ? $this->image_mobile
            : $this->image_desktop;
    }
}