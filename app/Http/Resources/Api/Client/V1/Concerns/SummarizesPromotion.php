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
                // The effective share of the price: the promotion value, or less when
                // max_discount_amount caps it. One decimal and rounded down, so a
                // badge never advertises more than the real discount.
                'discount_percentage' => $isFixed ? null : ($unitPrice > 0 && $this->promotion_discount > 0
                    ? max(0.1, floor(round($this->promotion_discount / $unitPrice * 1000, 6)) / 10)
                    : 0),
                'final_price' => round($unitPrice - $this->promotion_discount, 2),
            ];
        });
    }

    /**
     * The `site_promotions` block: untargeted codes a shopper enters at checkout,
     * the same list on every product. They discount the whole order, so unlike
     * `promotion` there is no per-unit discount or final price, and the minimum
     * order amount is checked against the cart. Missing when the endpoint did not
     * look promotions up; an empty list when none are live.
     *
     * @return list<array<string, mixed>>|MissingValue
     */
    protected function siteWidePromotionSummaries(): array|MissingValue
    {
        return $this->when($this->resource->relationLoaded('siteWidePromotions'), fn (): array => $this->siteWidePromotions
            ->map(fn ($promotion): array => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'code' => $promotion->code,
                'type' => $promotion->type,
                'kind' => $promotion->kind,
                'value' => (float) $promotion->value,
                'min_order_amount' => $promotion->min_order_amount !== null ? (float) $promotion->min_order_amount : null,
                'max_discount_amount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
                'expires_at' => $promotion->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all());
    }
}
