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
        'supplier_id',
        'unit_cost',
        'created_by_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Includes soft-deleted suppliers, so history keeps naming who a receipt
     * came from after that supplier is removed.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
