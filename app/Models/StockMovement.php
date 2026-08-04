<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'movement_type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reason',
        'source_location',
        'destination_location',
        'reference',
        'metadata',
        'created_by_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
