<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'display_name',
        'unit',
        'is_required',
        'is_filterable',
        'is_searchable',
        'is_variant',
        'validation_rules',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'validation_rules' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }
}
