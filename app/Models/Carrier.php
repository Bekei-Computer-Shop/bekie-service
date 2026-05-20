<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'logo',
        'website',
        'tracking_url',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
