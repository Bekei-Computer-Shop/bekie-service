<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
    /** @var Collection<int, Coupon>|null */
    private ?Collection $storefrontPromotions = null;

    /**
     * @param  Collection<array-key, array{product: ?Product, quantity: int, unit_price: float, variant_id?: ?int}>  $lines  keyed by the caller; keys are preserved
     * @param  list<int>  $exceptCouponIds  promotions to skip, e.g. a coupon already applied by code
     * @return Collection<array-key, array{subtotal: float, discount: float, total: float, unit_price: float, promotion: ?Coupon, discount_source: ?string}>
     */
    public function priceLines(Collection $lines, ?int $userId = null, array $exceptCouponIds = []): Collection
    {
        $products = $lines->pluck('product')->filter();
        $productIds = $products->pluck('id')->unique()->values()->all();
        $variantIds = $lines->pluck('variant_id')->filter()->unique()->values()->all();
        $categoryIds = $products->pluck('category_id')->filter()->unique()->values()->all();
        $categoryAncestorIds = $this->categoryAncestorIds($categoryIds);
        $orderSubtotal = round((float) $lines->sum(fn (array $line): float => $line['quantity'] * $line['unit_price']), 2);
        $categoryTargetIds = array_values(array_unique(array_merge(...array_values($categoryAncestorIds ?: [[]]))));
        $promotions = $this->matchingPromotions($productIds, $variantIds, $categoryTargetIds);
        $userUses = $this->userUsesByCoupon($promotions, $userId);
        $userGroupIds = $userId === null ? [] : User::query()
            ->whereKey($userId)
            ->whereHas('customerGroups', fn ($query) => $query->where('is_active', true))
            ->with(['customerGroups' => fn ($query) => $query->where('is_active', true)->select('customer_groups.id')])
            ->first()?->customerGroups->pluck('id')->map(fn ($id): int => (int) $id)->all() ?? [];
        $isVipCustomer = $this->isVipCustomer($userId);

        return $lines->map(function (array $line) use ($promotions, $userUses, $userGroupIds, $exceptCouponIds, $isVipCustomer, $orderSubtotal, $categoryAncestorIds): array {
            $subtotal = round($line['quantity'] * $line['unit_price'], 2);
            $best = null;
            $bestAmount = 0.0;

            foreach ($promotions as $promotion) {
                if (in_array($promotion->id, $exceptCouponIds, true)
                    || ! $this->isApplicable($promotion, $line, $userUses[$promotion->id] ?? 0, $userGroupIds, $orderSubtotal, $categoryAncestorIds)) {
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
                'unit_price' => (float) $line['unit_price'],
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
     * @return Collection<int, array{subtotal: float, discount: float, total: float, unit_price: float, promotion: ?Coupon, discount_source: ?string}>
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
                'unit_price' => $this->currentUnitPrice($item),
                'variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
            ],
        ]);

        return $this->priceLines($lines, (int) $cart->user_id, $couponId ? [(int) $couponId] : []);
    }

    private function currentUnitPrice(CartItem $item): float
    {
        return (float) (
            $item->variant?->sale_price
            ?? $item->product?->sale_price
            ?? $item->variant?->price
            ?? $item->product?->price
            ?? $item->unit_price
        );
    }

    /**
     * Product ids and category ids reached by live public promotions, for
     * finding products to list as on promotion.
     *
     * @return array{product_ids: list<string>, category_ids: list<int>}
     */
    public function storefrontTargets(): array
    {
        $promotions = $this->storefrontPromotions();

        return [
            'product_ids' => $promotions->flatMap(fn (Coupon $promotion) => $promotion->products->pluck('id')
                ->merge($promotion->variants->pluck('product_id')))->unique()->values()->all(),
            'category_ids' => $promotions->flatMap(fn (Coupon $promotion) => $promotion->storefront_category_ids)
                ->unique()->values()->all(),
        ];
    }

    /**
     * Sets `activePromotion` and `promotion_discount` on each product: the live
     * public promotion worth the most on one unit at its current price, or null.
     * A variant target counts for its product.
     *
     * @param  iterable<Product>  $products
     */
    public function attachStorefrontPromotions(iterable $products): void
    {
        $promotions = $this->storefrontPromotions();

        foreach ($products as $product) {
            $unitPrice = (float) ($product->sale_price ?? $product->price);
            $best = $promotions
                ->filter(fn (Coupon $promotion): bool => $promotion->products->contains('id', $product->id)
                    || $promotion->variants->contains('product_id', $product->id)
                    || in_array((int) $product->category_id, $promotion->storefront_category_ids, true))
                ->sortByDesc(fn (Coupon $promotion): float => $this->amountFor($promotion, $unitPrice, 1))
                ->first();

            $product->setRelation('activePromotion', $best);
            $product->promotion_discount = $best ? $this->amountFor($best, $unitPrice, 1) : null;
        }
    }

    /**
     * Live promotions any shopper could get: active, inside their window,
     * under their usage limit, targeted at products, variants or categories,
     * and not limited to customer groups. Memoised for the request.
     *
     * @return Collection<int, Coupon>
     */
    private function storefrontPromotions(): Collection
    {
        return $this->storefrontPromotions ??= Coupon::query()
            ->active()
            ->valid()
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->whereDoesntHave('customerGroups')
            ->where(fn ($query) => $query->has('products')->orHas('variants')->orHas('categories'))
            ->with(['products:id', 'variants:id,product_id', 'categories:id'])
            ->get()
            ->each(function (Coupon $promotion): void {
                $promotion->storefront_category_ids = $this->reachedCategoryIds($promotion);
            });
    }

    /**
     * The categories a promotion names, plus all their descendants when it
     * includes subcategories.
     *
     * @return list<int>
     */
    private function reachedCategoryIds(Coupon $promotion): array
    {
        $ids = $promotion->categories->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $frontier = $promotion->include_subcategories ? $ids : [];

        while ($frontier !== []) {
            $frontier = array_values(array_diff(
                Category::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $ids,
            ));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
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

                $lockedPromotion = Coupon::query()->lockForUpdate()->findOrFail($promotion->id);
                $userUses = CouponUsage::query()
                    ->where('coupon_id', $lockedPromotion->id)
                    ->where('user_id', $order->user_id)
                    ->count();

                if (! $lockedPromotion->isValid() || ! $lockedPromotion->canBeUsedByUser($userUses)) {
                    throw ValidationException::withMessages([
                        'promotion' => ['A promotion is no longer available. Please review the order total.'],
                    ]);
                }

                $updated = Coupon::query()
                    ->whereKey($lockedPromotion->id)
                    ->where(function ($query): void {
                        $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
                    })
                    ->increment('used_count');

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        'promotion' => ['A promotion has reached its usage limit. Please review the order total.'],
                    ]);
                }

                CouponUsage::create([
                    'coupon_id' => $promotion->id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'coupon_code' => $promotion->code,
                    'discount_amount' => round((float) $lines->sum('discount'), 2),
                    'used_at' => now(),
                    'metadata' => ['source' => 'product_promotion'],
                ]);

            });
    }

    public function couponCodeDiscount(Coupon $coupon, int $userId, float $orderSubtotal): float
    {
        if (! $coupon->isValid()
            || $orderSubtotal < (float) ($coupon->min_order_amount ?? 0)
            || ! $coupon->canBeUsedByUser(CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count())) {
            return 0.0;
        }

        $allowedGroupIds = $coupon->customerGroups()->pluck('customer_groups.id');
        if ($allowedGroupIds->isNotEmpty()
            && User::query()->whereKey($userId)->whereHas('customerGroups', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('customer_groups.id', $allowedGroupIds))->doesntExist()) {
            return 0.0;
        }

        return $coupon->calculateDiscount($orderSubtotal);
    }

    public function recordCouponCodeUsage(Order $order, Coupon $coupon, float $discount): void
    {
        $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
        $userUses = CouponUsage::query()
            ->where('coupon_id', $lockedCoupon->id)
            ->where('user_id', $order->user_id)
            ->count();

        if (! $lockedCoupon->isValid() || ! $lockedCoupon->canBeUsedByUser($userUses)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is no longer available. Please review the order total.'],
            ]);
        }

        $updated = Coupon::query()
            ->whereKey($lockedCoupon->id)
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has reached its usage limit. Please review the order total.'],
            ]);
        }

        CouponUsage::create([
            'coupon_id' => $lockedCoupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'coupon_code' => $lockedCoupon->code,
            'discount_amount' => $discount,
            'used_at' => now(),
        ]);
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

    private function isApplicable(Coupon $promotion, array $line, int $userUses, array $userGroupIds, float $orderSubtotal, array $categoryAncestorIds): bool
    {
        if (! $promotion->isValid() || ! $promotion->canBeUsedByUser($userUses)) {
            return false;
        }

        if ($orderSubtotal < (float) ($promotion->min_order_amount ?? 0)) {
            return false;
        }

        if ($promotion->customerGroups->isNotEmpty()
            && $promotion->customerGroups->pluck('id')->intersect($userGroupIds)->isEmpty()) {
            return false;
        }

        $product = $line['product'];
        $hasTargets = $promotion->products->isNotEmpty()
            || $promotion->categories->isNotEmpty()
            || $promotion->variants->isNotEmpty();

        if (! $hasTargets) {
            return true;
        }

        if ($product && $promotion->products->contains('id', $product->id)) {
            return true;
        }

        if (! empty($line['variant_id']) && $promotion->variants->contains('id', (int) $line['variant_id'])) {
            return true;
        }

        $categoryId = $product?->category_id;
        if ($categoryId !== null) {
            $matchingCategoryIds = $promotion->include_subcategories
                ? ($categoryAncestorIds[(int) $categoryId] ?? [(int) $categoryId])
                : [(int) $categoryId];

            if ($promotion->categories->pluck('id')->intersect($matchingCategoryIds)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $productIds
     * @param  list<int>  $variantIds
     * @param  list<int>  $categoryAncestorIds
     */
    private function matchingPromotions(array $productIds, array $variantIds, array $categoryAncestorIds): Collection
    {
        $query = Coupon::query()->with(['products:id', 'categories:id', 'variants:id', 'customerGroups:id']);
        $query->where(function ($targets) use ($productIds, $variantIds, $categoryAncestorIds): void {
            $hasCandidate = false;
            if ($productIds !== []) {
                $targets->whereHas('products', fn ($products) => $products->whereIn('products.id', $productIds));
                $hasCandidate = true;
            }
            if ($variantIds !== []) {
                $method = $hasCandidate ? 'orWhereHas' : 'whereHas';
                $targets->{$method}('variants', fn ($variants) => $variants->whereIn('product_variants.id', $variantIds));
                $hasCandidate = true;
            }
            if ($categoryAncestorIds !== []) {
                $method = $hasCandidate ? 'orWhereHas' : 'whereHas';
                $targets->{$method}('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryAncestorIds));
                $hasCandidate = true;
            }

            if (! $hasCandidate) {
                $targets->whereRaw('1 = 0');
            }
            $targets->orWhere(fn ($groupTarget) => $groupTarget
                ->whereDoesntHave('products')
                ->whereDoesntHave('variants')
                ->whereDoesntHave('categories')
                ->whereHas('customerGroups'));
        });

        return $query->orderBy('id')->get();
    }

    /** @param list<int> $categoryIds
     * @return array<int, list<int>>
     */
    private function categoryAncestorIds(array $categoryIds): array
    {
        $ancestors = [];
        foreach ($categoryIds as $categoryId) {
            $ancestors[$categoryId] = [$categoryId];
        }

        $frontier = array_fill_keys($categoryIds, $categoryIds);
        while ($frontier !== []) {
            $parents = Category::query()->whereIn('id', array_keys($frontier))->pluck('parent_id', 'id');
            $next = [];

            foreach ($frontier as $nodeId => $roots) {
                $parentId = $parents[$nodeId] ?? null;
                if ($parentId === null) {
                    continue;
                }

                $parentId = (int) $parentId;
                foreach ($roots as $rootId) {
                    if (! in_array($parentId, $ancestors[$rootId], true)) {
                        $ancestors[$rootId][] = $parentId;
                        $next[$parentId][] = $rootId;
                    }
                }
            }

            foreach ($next as $parentId => $roots) {
                $next[$parentId] = array_values(array_unique($roots));
            }
            $frontier = $next;
        }

        return $ancestors;
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

    public function amountFor(Coupon $promotion, float $lineSubtotal, int $quantity): float
    {
        $value = (float) $promotion->value;

        $amount = $promotion->type === 'fixed'
            ? $value * $quantity
            : $lineSubtotal * $value / 100;

        if ($promotion->max_discount_amount !== null) {
            $amount = min($amount, (float) $promotion->max_discount_amount);
        }

        return round(max(0, min($amount, $lineSubtotal)), 2);
    }

    /**
     * How many times this customer has already used each promotion that has a
     * per-customer limit. Promotions without one are not counted.
     *
     * @param  Collection<int, Coupon>  $promotions
     * @return array<int, int>
     */
    private function userUsesByCoupon(Collection $promotions, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $limited = $promotions
            ->filter(fn (Coupon $promotion): bool => $promotion->user_limit !== null)
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
