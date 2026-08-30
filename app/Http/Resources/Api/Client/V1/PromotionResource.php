<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PromotionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'description' => $this->description,
            'banner_image' => $this->resolveImage($this->banner_image),
            'min_order_amount' => $this->min_order_amount !== null ? (float) $this->min_order_amount : null,
            'max_discount_amount' => $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'applicable_products' => $this->applicable_products,
            'applicable_categories' => $this->applicable_categories,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            // Aggregate capacity, useful for "only 5 left" storefront urgency.
            // The exact redemption ledger stays admin-only.
            'usage_remaining' => $this->usage_limit !== null
                ? max(0, (int) $this->usage_limit - (int) $this->used_count)
                : null,
        ];
    }

    /**
     * Uploads normally hold an absolute URL (Cloudinary) that must pass through
     * untouched; only a relative path is resolved against the public disk.
     */
    private function resolveImage(?string $image): ?string
    {
        if ($image === null || $image === '' || preg_match('#^(https?://|data:|//)#i', $image) === 1) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}