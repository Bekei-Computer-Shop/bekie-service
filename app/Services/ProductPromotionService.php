<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Prices order lines against the promotions attached to their products — the
 * `coupon_product` pivot the product form writes. Promotions are `Coupon` rows.
 *
 * Rules, per order line:
 *  - Only the single most valuable promotion applies. They do not stack, so two
 *    overlapping campaigns on a product cannot take it below what the better
 *    one intends.
 *  - A promotion must be active, inside its window and under its usage limits.
 *    Once attached to a product, its configured discount applies to that
 *    product's order lines regardless of campaign kind or minimum order amount.
 *  - Percentage promotions take that share of the line subtotal. Fixed ones
 *    take their value off each unit, so buying three takes it three times.
 *  - `max_discount_amount` caps the line's discount; the discount never exceeds
 *    the line subtotal.
 *  - VIP customers receive 5% off lines without an applicable product
 *    promotion. The discounts never stack on the same line.
 *
 * The result is a plain calculation: nothing is written until recordUsage().
 */
class ProductPromotionService
{
    /**
     * @param  Collection<array-key, array{product: ?Product, quantity: int, unit_price: float}>  $lines  keyed by the caller; keys are preserved
     * @param  list<int>  $exceptCouponIds  promotions to skip, e.g. a coupon already applied by code
     * @return Collection<array-key, array{subtotal: float, discount: float, total: float, promotion: ?Coupon, discount_source: ?string}>
     */
    public function priceLines(Collection $lines, ?int $userId = null, array $exceptCouponIds = []): Collection
    {
        // One query for every product's promotions rather than one per line.
        (new EloquentCollection($lines->pluck('product')->filter()->all()))->loadMissing('promotions');

        $userUses = $this->userUsesByCoupon($lines, $userId);
        $isVipCustomer = $this->isVipCustomer($userId);

        return $lines->map(function (array $line) use ($userUses, $exceptCouponIds, $isVipCustomer): array {
            $subtotal = round($line['quantity'] * $line['unit_price'], 2);
            $best = null;
            $bestAmount = 0.0;

            foreach ($line['product']?->promotions ?? [] as $promotion) {
                if (in_array($promotion->id, $exceptCouponIds, true)
                    || ! $this->isApplicable($promotion, $userUses[$promotion->id] ?? 0)) {
                    continue;
                }

                $amount = $this->amountFor($promotion, $subtotal, $line['quantity']);

                if ($amount > $bestAmount) {
                    $best = $promotion;
                    $bestAmount = $amount;
                }
            }

            if ($best === null && $isVipCustomer) {
                $bestAmount = round($subtotal * 0.05, 2);
            }

            return [
                'subtotal' => $subtotal,
                'discount' => $bestAmount,
                'total' => round($subtotal - $bestAmount, 2),
                'promotion' => $best,
                'discount_source' => $best !== null ? 'promotion' : ($bestAmount > 0 ? 'vip' : null),
            ];
        });
    }

    /**
     * Prices a cart's items, keyed by cart item id. A coupon the customer
     * already applied by code is left out, so one coupon that is also attached
     * to a product is not taken off twice.
     *
     * @return Collection<int, array{subtotal: float, discount: float, total: float, promotion: ?Coupon, discount_source: ?string}>
     */
    public function priceCart(Cart $cart): Collection
    {
        $couponId = $cart->coupon_code
            ? Coupon::query()->where('code', $cart->coupon_code)->value('id')
            : null;

        $lines = $cart->items->mapWithKeys(fn (CartItem $item): array => [
            $item->id => [
                'product' => $item->product,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ],
        ]);

        return $this->priceLines($lines, (int) $cart->user_id, $couponId ? [(int) $couponId] : []);
    }

    /**
     * Counts each promotion that was applied against its usage limits: one
     * usage row and one `used_count` per promotion per order, however many
     * lines it discounted.
     *
     * @param  Collection<array-key, array{subtotal: float, discount: float, total: float, promotion: ?Coupon, discount_source: ?string}>  $priced
     */
    public function recordUsage(Order $order, Collection $priced): void
    {
        $priced
            ->filter(fn (array $line): bool => $line['promotion'] !== null)
            ->groupBy(fn (array $line): int => $line['promotion']->id)
            ->each(function (Collection $lines) use ($order): void {
                /** @var Coupon $promotion */
                $promotion = $lines->first()['promotion'];

                CouponUsage::create([
                    'coupon_id' => $promotion->id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'coupon_code' => $promotion->code,
                    'discount_amount' => round((float) $lines->sum('discount'), 2),
                    'used_at' => now(),
                    'metadata' => ['source' => 'product_promotion'],
                ]);

                $promotion->incrementUsage();
            });
    }

    /**
     * The snapshot stored on the order item, so the line still says which
     * promotion discounted it after the promotion is edited or deleted.
     *
     * @param  array{promotion: ?Coupon, discount_source?: ?string}  $priced
     * @return array<string, mixed>|null
     */
    public function snapshot(array $priced): ?array
    {
        $promotion = $priced['promotion'];

        if ($promotion === null) {
            return ($priced['discount_source'] ?? null) === 'vip'
                ? [
                    'source' => 'vip',
                    'name' => 'VIP Discount',
                    'type' => 'percentage',
                    'value' => '5.00',
                ]
                : null;
        }

        return [
            'source' => 'promotion',
            'id' => $promotion->id,
            'name' => $promotion->name,
            'code' => $promotion->code,
            'type' => $promotion->type,
            'value' => number_format((float) $promotion->value, 2, '.', ''),
        ];
    }

    private function isApplicable(Coupon $promotion, int $userUses): bool
    {
        return $promotion->isValid()
            && $promotion->canBeUsedByUser($userUses);
    }

    private function isVipCustomer(?int $userId): bool
    {
        return $userId !== null && User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->whereHas('customerGroups', fn ($query) => $query->where('slug', CustomerGroup::VIP_SLUG))
            ->exists();
    }

    private function amountFor(Coupon $promotion, float $lineSubtotal, int $quantity): float
    {
        $value = (float) $promotion->value;

        $amount = $promotion->type === 'fixed'
            ? $value * $quantity
            : $lineSubtotal * $value / 100;

        if ($promotion->max_discount_amount) {
            $amount = min($amount, (float) $promotion->max_discount_amount);
        }

        return round(max(0, min($amount, $lineSubtotal)), 2);
    }

    /**
     * How many times this customer has already used each promotion that has a
     * per-customer limit. Promotions without one are not counted.
     *
     * @param  Collection<array-key, array{product: ?Product}>  $lines
     * @return array<int, int>
     */
    private function userUsesByCoupon(Collection $lines, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $limited = $lines
            ->flatMap(fn (array $line) => $line['product']?->promotions ?? [])
            ->filter(fn (Coupon $promotion): bool => (bool) $promotion->user_limit)
            ->pluck('id')
            ->unique();

        if ($limited->isEmpty()) {
            return [];
        }

        return CouponUsage::query()
            ->where('user_id', $userId)
            ->whereIn('coupon_id', $limited->all())
            ->selectRaw('coupon_id, count(*) as uses')
            ->groupBy('coupon_id')
            ->pluck('uses', 'coupon_id')
            ->map(fn ($uses): int => (int) $uses)
            ->all();
    }
}
