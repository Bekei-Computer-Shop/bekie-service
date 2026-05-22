<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'coupon_code' => $this->coupon_code,
            'status' => $this->status,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'expires_at' => $this->expires_at,
            'last_activity_at' => $this->last_activity_at,
            'metadata' => $this->metadata,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
