<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'address_id' => $this->address_id,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'shipping_status' => $this->shipping_status,
            'tracking_number' => $this->tracking_number,
            'shipping_provider' => $this->shipping_provider,
            'customer_snapshot' => $this->customer_snapshot,
            'address_snapshot' => $this->address_snapshot,
            'metadata' => $this->metadata,
            'paid_at' => $this->paid_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'refunded_at' => $this->refunded_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
