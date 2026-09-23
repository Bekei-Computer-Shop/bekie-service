<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
                'thumbnail' => $this->product?->thumbnail,
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'name' => $this->variant?->name,
            ],
            'order' => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'created_at' => $this->order?->created_at?->toIso8601String(),
            ],
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'warranty' => [
                'status' => $this->warranty_status,
                'start_at' => $this->warranty_start_at?->toIso8601String(),
                'end_at' => $this->warranty_end_at?->toIso8601String(),
                'days_remaining' => $this->warranty_end_at ? max(0, now()->diffInDays($this->warranty_end_at, absolute: false)) : null,
                'is_active' => $this->warranty_status === 'active',
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
