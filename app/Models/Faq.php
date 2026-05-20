<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Faq extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'question',
        'answer',
        'category',
        'is_active',
        'is_featured',
        'sort_order',
        'slug',
        'views',
        'helpful_count',
        'not_helpful_count',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'views' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | BOOT (AUTO SLUG GENERATION)
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($faq) {
            if (empty($faq->slug)) {
                $faq->slug = Str::slug($faq->question);
            }
        });
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

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
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

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    public function helpfulScore(): int
    {
        return $this->helpful_count - $this->not_helpful_count;
    }

    public function isPopular(): bool
    {
        return $this->views > 100;
    }

    public function excerpt(int $limit = 120): string
    {
        return Str::limit(strip_tags($this->answer), $limit);
    }
}