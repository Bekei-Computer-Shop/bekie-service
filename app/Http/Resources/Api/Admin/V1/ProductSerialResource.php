<?php

namespace App\Http\Resources\Api\Admin\V1;

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
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'name' => $this->variant?->name,
            ],
            'warehouse' => $this->warehouse,
            'receiving_reference' => $this->receiving_reference,
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'email' => $this->customer?->email,
            ],
            'order' => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
            ],
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'warranty' => [
                'status' => $this->warranty_status,
                'start_at' => $this->warranty_start_at?->toIso8601String(),
                'end_at' => $this->warranty_end_at?->toIso8601String(),
            ],
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
