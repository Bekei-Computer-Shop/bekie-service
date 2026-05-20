<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'variant_name' => $this->variant_name,
            'variant_attributes' => $this->variant_attributes,
            'quantity_shipped' => $this->quantity_shipped,
            'quantity_refunded' => $this->quantity_refunded,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
