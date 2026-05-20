<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'shipping_method_id',
        'carrier_id',
        'tracking_number',
        'status',
        'weight',
        'shipping_cost',
        'estimated_days',

        'packed_at',
        'shipped_at',
        'in_transit_at',
        'delivered_at',
        'failed_at',

        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'weight' => 'decimal:2',
        'shipping_cost' => 'decimal:2',

        'packed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['delivered', 'failed', 'returned']);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeTracking(Builder $query, string $trackingNumber): Builder
    {
        return $query->where('tracking_number', $trackingNumber);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function markAsShipped(): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    public function markAsInTransit(): void
    {
        $this->update([
            'status' => 'in_transit',
            'in_transit_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            'pending',
            'processing',
            'packed',
            'shipped',
            'in_transit',
        ]);
    }
}