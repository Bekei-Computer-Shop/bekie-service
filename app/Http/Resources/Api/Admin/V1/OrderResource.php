<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // `id` stays the uuid — routes bind on it (Order::getRouteKeyName).
            'id' => $this->uuid ?? $this->id,
            // The human-facing reference staff and customers actually quote.
            'order_number' => $this->order_number,
            'status' => $this->status,
            // Cast before formatting: Postgres returns decimal columns as
            // strings, which number_format() rejects under strict_types.
            'total' => number_format((float) $this->grand_total, 2, '.', ''),
            // Breakdown behind the total, for the order detail summary panel.
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
            'discount_total' => number_format((float) $this->discount_total, 2, '.', ''),
            'tax_total' => number_format((float) $this->tax_total, 2, '.', ''),
            'shipping_total' => number_format((float) $this->shipping_total, 2, '.', ''),
            'currency' => $this->currency,
            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'transaction_id' => $this->transaction_id,
            ],
            'notes' => $this->notes,
            'customer' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],
            // Snapshot taken at checkout; there is no addresses data yet, so
            // this is usually null and the UI falls back to a placeholder.
            'shipping_address' => $this->address_snapshot,
            'tracking' => [
                'status' => $this->shipping_status,
                'number' => $this->tracking_number,
                'provider' => $this->shipping_provider,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
