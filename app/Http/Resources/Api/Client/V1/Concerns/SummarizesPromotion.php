<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1\Concerns;

use App\Models\Coupon;
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
            $isFixed = $promotion->type === 'fixed';

            return [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'kind' => $promotion->kind,
                'value' => (float) $promotion->value,
                'min_order_amount' => $promotion->min_order_amount !== null ? (float) $promotion->min_order_amount : null,
                'max_discount_amount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
                'expires_at' => $promotion->expires_at?->toIso8601String(),
                // Only the figure matching the promotion type is set; the other is null.
                'discount_amount' => $isFixed ? $this->promotion_discount : null,
                // The promotion value, as the admin API returns it, unless
                // max_discount_amount caps it: then the effective share of the price.
                'discount_percentage' => $isFixed ? null : $this->discountPercentage($promotion, $unitPrice),
                'final_price' => round($unitPrice - $this->promotion_discount, 2),
            ];
        });
    }

    /**
     * An uncapped percentage is the configured value itself: working it back from
     * the discount, which is rounded to cents, would turn 10% of 33.33 into 9.9%.
     * Only when the cap really bites is the share worked out from the discount, to
     * one decimal and rounded down, so a badge never advertises more than the real
     * discount.
     */
    private function discountPercentage(Coupon $promotion, float $unitPrice): float|int
    {
        if ($unitPrice <= 0 || $this->promotion_discount <= 0) {
            return 0;
        }

        $value = (float) $promotion->value;
        $capped = $promotion->max_discount_amount !== null
            && $unitPrice * $value / 100 > (float) $promotion->max_discount_amount;

        return $capped
            ? max(0.1, floor(round($this->promotion_discount / $unitPrice * 1000, 6)) / 10)
            : $value;
    }
}
