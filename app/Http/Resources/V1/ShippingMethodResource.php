<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'price_per_kg' => $this->price_per_kg,
            'min_weight' => $this->min_weight,
            'max_weight' => $this->max_weight,
            'min_delivery_days' => $this->min_delivery_days,
            'max_delivery_days' => $this->max_delivery_days,
            'is_active' => $this->is_active,
            'type' => $this->type,
            'sort_order' => $this->sort_order,
            'estimated_delivery' => $this->getEstimatedDeliveryAttribute(),
        ];
    }
}
