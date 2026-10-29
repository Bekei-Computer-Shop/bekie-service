<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1\Concerns;

use Illuminate\Http\Resources\MissingValue;

/**
 * The `promotion` block on storefront products: the best live promotion for
 * one unit, as set by ProductPromotionService::attachStorefrontPromotions().
 * Missing when the endpoint did not look promotions up; null when none apply.
 */
trait SummarizesPromotion
{
    /** @return array<string, mixed>|null|MissingValue */
    protected function promotionSummary(): array|null|MissingValue
    {
        return $this->when($this->resource->relationLoaded('activePromotion'), function (): ?array {
            $promotion = $this->activePromotion;
            if ($promotion === null) {
                return null;
            }

            $unitPrice = (float) ($this->sale_price ?? $this->price);

            return [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => (float) $promotion->value,
                'min_order_amount' => $promotion->min_order_amount !== null ? (float) $promotion->min_order_amount : null,
                'max_discount_amount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
                'expires_at' => $promotion->expires_at?->toIso8601String(),
                'discount_amount' => $this->promotion_discount,
                'discount_percentage' => $unitPrice > 0 && $this->promotion_discount > 0 ? max(1,(int) round($this->promotion_discount / $unitPrice * 100)) : 0,
                'final_price' => round($unitPrice - $this->promotion_discount, 2),
            ];
        });
    }
}
