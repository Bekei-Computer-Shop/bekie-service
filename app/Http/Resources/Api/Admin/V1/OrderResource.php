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
            // The coupon / promotion applied at checkout. `code` is the snapshot
            // and is always present when one was used; the rest is filled from
            // the live coupon while it still exists (eager-loaded by the
            // controller, so no N+1 here).
            'coupon' => $this->couponPayload(),
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
            // Snapshot captured at checkout is the source of truth. Orders that
            // never captured one (legacy rows, seeded data) fall back to the
            // customer's current default address so the panel isn't just a dash.
            'shipping_address' => $this->address_snapshot ?: $this->fallbackShippingAddress(),
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

    /**
     * The coupon block for the order summary panel, or null when no coupon was
     * used. `type`/`value` come from the live coupon and are null once it has
     * been deleted — `code` and `discount_total` still tell the story.
     *
     * @return array<string, mixed>|null
     */
    private function couponPayload(): ?array
    {
        if (! $this->coupon_code) {
            return null;
        }

        $coupon = $this->relationLoaded('coupon') ? $this->coupon : null;

        return [
            'code' => $this->coupon_code,
            'name' => $coupon?->name,
            'type' => $coupon?->type,
            'value' => $coupon !== null ? number_format((float) $coupon->value, 2, '.', '') : null,
            'discount_total' => number_format((float) $this->discount_total, 2, '.', ''),
        ];
    }

    /**
     * Snapshot-shaped array built from the customer's current default address,
     * used only when the order has no `address_snapshot` of its own. Returns
     * null unless `user.defaultAddress` is eager-loaded, so the orders list
     * (which does not load it, and does not render an address) stays N+1-free.
     *
     * @return array<string, mixed>|null
     */
    private function fallbackShippingAddress(): ?array
    {
        if (! $this->relationLoaded('user') || ! $this->user?->relationLoaded('defaultAddress')) {
            return null;
        }

        $address = $this->user->defaultAddress;

        if (! $address) {
            return null;
        }

        return [
            'label' => $address->label,
            'full_name' => $address->full_name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
        ];
    }
}
