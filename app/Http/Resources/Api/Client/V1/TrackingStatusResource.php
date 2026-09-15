<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->shipping_status,
            'status_label' => $this->getStatusLabel(),
            'tracking_number' => $this->tracking_number,
            'shipping_provider' => $this->shipping_provider,
            'events' => $this->getTrackingEvents(),
            'estimated_delivery' => $this->getEstimatedDelivery(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match ($this->shipping_status) {
            'pending' => 'Pending Shipment',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            default => 'Unknown Status',
        };
    }

    private function getTrackingEvents(): array
    {
        $events = [];

        // Order created event
        $events[] = [
            'status' => 'order_created',
            'label' => 'Order Confirmed',
            'timestamp' => $this->created_at?->toIso8601String(),
            'completed' => true,
        ];

        // Payment status
        if ($this->paid_at) {
            $events[] = [
                'status' => 'payment_confirmed',
                'label' => 'Payment Confirmed',
                'timestamp' => $this->paid_at->toIso8601String(),
                'completed' => true,
            ];
        }

        // Shipped event
        if ($this->shipped_at) {
            $events[] = [
                'status' => 'shipped',
                'label' => 'Order Shipped',
                'timestamp' => $this->shipped_at->toIso8601String(),
                'completed' => true,
            ];
        } else {
            $events[] = [
                'status' => 'shipped',
                'label' => 'Order Shipped',
                'timestamp' => null,
                'completed' => false,
            ];
        }

        // Delivered event
        if ($this->delivered_at) {
            $events[] = [
                'status' => 'delivered',
                'label' => 'Order Delivered',
                'timestamp' => $this->delivered_at->toIso8601String(),
                'completed' => true,
            ];
        } else {
            $events[] = [
                'status' => 'delivered',
                'label' => 'Order Delivered',
                'timestamp' => null,
                'completed' => false,
            ];
        }

        return $events;
    }

    private function getEstimatedDelivery(): ?string
    {
        // If already delivered, return null
        if ($this->delivered_at) {
            return null;
        }

        // If not yet shipped, return null (can't estimate)
        if (! $this->shipped_at) {
            return null;
        }

        // Estimate 3-5 business days from ship date
        $estimatedDays = 3;
        $estimatedDate = $this->shipped_at->addDays($estimatedDays);

        return $estimatedDate->toIso8601String();
    }
}
