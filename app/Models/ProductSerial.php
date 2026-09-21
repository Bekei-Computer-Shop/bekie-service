<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSerial extends Model
{
    public const AVAILABLE = 'available';

    public const RESERVED = 'reserved';

    public const SOLD = 'sold';

    public const RETURNED = 'returned';

    public const IN_SERVICE = 'in_service';

    public const REPAIRED = 'repaired';

    public const REPLACED = 'replaced';

    public const DAMAGED = 'damaged';

    public const LOST = 'lost';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'product_id', 'product_variant_id', 'serial_number', 'status', 'warehouse',
        'receiving_reference', 'order_id', 'customer_id', 'purchased_at',
        'warranty_start_at', 'warranty_end_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'warranty_start_at' => 'datetime',
            'warranty_end_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProductSerialHistory::class)->latest();
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (! $this->warranty_end_at) {
            return 'not_covered';
        }

        return $this->warranty_end_at->isFuture() ? 'active' : 'expired';
    }
}
