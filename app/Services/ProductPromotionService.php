<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
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
 *  - A promotion must be active, inside its window, under its usage limits and
 *    a plain price cut (see Coupon::appliesToProductPrice()).
 *  - Percentage promotions take that share of the line subtotal. Fixed ones
 *    take their value off each unit, so buying three takes it three times.
 *  - `max_discount_amount` caps the line's discount; the discount never exceeds
 *    the line subtotal.
 *  - `min_order_amount` is checked against the whole order's subtotal before
 *    any promotion, not the single line — it is an order threshold.
 *
 * The result is a plain calculation: nothing is written until recordUsage().
 */
class ProductPromotionService
{
    /**
     * @param  Collection<array-key, array{product: ?Product, quantity: int, unit_price: float}>  $lines  keyed by the caller; keys are preserved
     * @param  list<int>  $exceptCouponIds  promotions to skip, e.g. a coupon already applied by code
     * @return Collection<array-key, array{subtotal: float, discount: float, total: float, promotion: ?Coupon}>
     */
    public function priceLines(Collection $lines, ?int $userId = null, array $exceptCouponIds = []): Collection
    {
        // One query for every product's promotions rather than one per line.
        (new EloquentCollection($lines->pluck('product')->filter()->all()))->loadMissing('promotions');

        $orderSubtotal = round($lines->sum(fn (array $line): float => $line['quantity'] * $line['unit_price']), 2);
        $userUses = $this->userUsesByCoupon($lines, $userId);

        return $lines->map(function (array $line) use ($orderSubtotal, $userUses, $exceptCouponIds): array {
            $subtotal = round($line['quantity'] * $line['unit_price'], 2);
            $best = null;
            $bestAmount = 0.0;

            foreach ($line['product']?->promotions ?? [] as $promotion) {
                if (in_array($promotion->id, $exceptCouponIds, true)
                    || ! $this->isApplicable($promotion, $orderSubtotal, $userUses[$promotion->id] ?? 0)) {
                    continue;
                }

                $amount = $this->amountFor($promotion, $subtotal, $line['quantity']);

                if ($amount > $bestAmount) {
                    $best = $promotion;
                    $bestAmount = $amount;
                }
            }

            return [
                'subtotal' => $subtotal,
                'discount' => $bestAmount,
                'total' => round($subtotal - $bestAmount, 2),
                'promotion' => $best,
            ];
        });
    }

    /**
     * Prices a cart's items, keyed by cart item id. A coupon the customer
     * already applied by code is left out, so one coupon that is also attached
     * to a product is not taken off twice.
     *
     * @return Collection<int, array{subtotal: float, discount: float, total: float, promotion: ?Coupon}>
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
     * @param  Collection<array-key, array{subtotal: float, discount: float, total: float, promotion: ?Coupon}>  $priced
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
     * @param  array{promotion: ?Coupon}  $priced
     * @return array<string, mixed>|null
     */
    public function snapshot(array $priced): ?array
    {
        $promotion = $priced['promotion'];

        if ($promotion === null) {
            return null;
        }

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'code' => $promotion->code,
            'type' => $promotion->type,
            'value' => number_format((float) $promotion->value, 2, '.', ''),
        ];
    }

    private function isApplicable(Coupon $promotion, float $orderSubtotal, int $userUses): bool
    {
        return $promotion->appliesToProductPrice()
            && $promotion->isValid()
            && $promotion->canBeUsedByUser($userUses)
            && $orderSubtotal >= (float) ($promotion->min_order_amount ?? 0);
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
