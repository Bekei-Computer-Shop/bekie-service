<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 50 orders built from the live product catalog (ComputerShop50Seeder) and
 * the existing shoppers (CustomerSeeder), exercising the exact coupon flow
 * checkout uses — Coupon::isValid() / calculateDiscount(), a CouponUsage row,
 * and Coupon::incrementUsage() — so the Promotions feature can be verified
 * end to end from seed data rather than by hand through the storefront.
 *
 * Line items pick real product/variant stock and decrement it through the
 * normal OrderItem::deductStock() model event (no manual stock writes here),
 * so a run only ever spends stock that ComputerShop50Seeder actually seeded —
 * run that seeder first.
 *
 * Every row is tagged with metadata->seeded_by so it can be told apart from
 * other data and removed again; re-running is a no-op if tagged orders
 * already exist (see the message run() prints).
 *
 * Usage: php artisan db:seed --class=ComputerShop50OrderSeeder
 */
class ComputerShop50OrderSeeder extends Seeder
{
    private const TAG = 'computer-shop-50-order-seeder';

    private const ORDER_COUNT = 50;

    /** Every 4th order attempts a coupon — about a quarter of the run. */
    private const COUPON_EVERY = 4;

    private const STATUSES = UpdateOrderRequest::STATUSES;

    private const METHODS = ['cod', 'bank_transfer', 'stripe', 'paypal'];

    /** @var array<string, int> product uuid => remaining stock this run */
    private array $productStock = [];

    /** @var array<int, int> variant id => remaining stock this run */
    private array $variantStock = [];

    public function run(): void
    {
        $existing = Order::query()->withTrashed()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command->warn("Skipped: {$existing} seeded orders already exist (including any soft-deleted ones).");
            $this->command->line('Remove them for good if you want a fresh set:');
            $this->command->line("  delete from coupon_usages where order_id in (select id from orders where metadata->>'seeded_by' = '".self::TAG."');");
            $this->command->line("  delete from order_items where order_id in (select id from orders where metadata->>'seeded_by' = '".self::TAG."');");
            $this->command->line("  delete from orders where metadata->>'seeded_by' = '".self::TAG."';");

            return;
        }

        $products = Product::with('variants')->get()->filter(fn (Product $p) => $p->variants->isNotEmpty())->values();
        if ($products->isEmpty()) {
            $this->command->error('No products with variants found — run ComputerShop50Seeder first.');

            return;
        }

        $customers = User::query()
            ->whereIn('email', CustomerSeeder::shopperEmails())
            ->get()
            ->sortBy(fn (User $u) => array_search($u->email, CustomerSeeder::shopperEmails(), true))
            ->values();
        if ($customers->isEmpty()) {
            $this->command->error('No seeded customers found — run CustomerSeeder first.');

            return;
        }

        $coupons = Coupon::all();

        $this->seedStockPools($products);

        $couponRunStats = []; // code => ['uses' => int, 'discount' => float]
        $ordersWithCoupon = 0;
        $totalDiscount = 0.0;
        $created = 0;

        for ($i = 1; $i <= self::ORDER_COUNT; $i++) {
            $customer = $customers[$i % $customers->count()];
            $status = self::STATUSES[$i % count(self::STATUSES)];
            $method = self::METHODS[$i % count(self::METHODS)];
            $placedAt = now()->subDays($i * 3)->setTime(8 + ($i % 10), ($i * 13) % 60);

            $lines = $this->pickLines($products, $i);
            if (empty($lines)) {
                // Ran out of trackable stock — stop rather than seed empty orders.
                $this->command->warn("Stopped after {$created} orders: catalog stock exhausted.");
                break;
            }

            $subtotal = round(array_sum(array_column($lines, 'total')), 2);

            $coupon = null;
            $discount = 0.0;
            if ($i % self::COUPON_EVERY === 0) {
                $coupon = $this->pickCoupon($coupons, $subtotal);
                $discount = $coupon ? $coupon->calculateDiscount($subtotal) : 0.0;
            }

            $tax = round(($subtotal - $discount) * 0.10, 2);
            $shipping = $subtotal > 1000 ? 0.0 : 7.50;
            $grandTotal = round($subtotal - $discount + $tax + $shipping, 2);

            $order = Order::create([
                'order_number' => 'CPS-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'status' => $status,
                'currency' => 'USD',
                'payment_method' => $method,
                'payment_status' => $this->paymentStatus($status),
                'transaction_id' => in_array($method, ['stripe', 'paypal'], true) ? 'txn_'.uniqid() : null,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'tax_total' => $tax,
                'shipping_total' => $shipping,
                'grand_total' => $grandTotal,
                'shipping_status' => $this->shippingStatus($status),
                'tracking_number' => in_array($status, ['processing', 'completed'], true) ? 'TRK'.mt_rand(10000000, 99999999) : null,
                'shipping_provider' => in_array($status, ['processing', 'completed'], true) ? 'J&T Express' : null,
                'customer_snapshot' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'metadata' => ['seeded_by' => self::TAG],
            ]);

            $order->forceFill(array_merge(
                ['created_at' => $placedAt, 'updated_at' => $placedAt],
                $this->lifecycleTimestamps($status, $placedAt),
            ))->saveQuietly();

            foreach ($lines as $line) {
                // OrderItem::booted() deducts product + variant stock on create.
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unit'],
                    'subtotal' => $line['total'],
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $line['total'],
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'variant_name' => $line['variant']->name,
                    'variant_attributes' => $line['variant']->attributes,
                ]);
            }

            if ($coupon && $discount > 0) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $customer->id,
                    'order_id' => $order->id,
                    'coupon_code' => $coupon->code,
                    'discount_amount' => $discount,
                    'used_at' => $placedAt,
                ]);
                $coupon->incrementUsage();

                $ordersWithCoupon++;
                $totalDiscount += $discount;
                $couponRunStats[$coupon->code] ??= ['uses' => 0, 'discount' => 0.0];
                $couponRunStats[$coupon->code]['uses']++;
                $couponRunStats[$coupon->code]['discount'] += $discount;
            }

            $created++;
        }

        $this->report($created, $ordersWithCoupon, $totalDiscount, $couponRunStats);
    }

    /*
    |--------------------------------------------------------------------------
    | Stock pools
    |--------------------------------------------------------------------------
    |
    | Line selection has to know how much is actually left to sell *before*
    | calling OrderItem::create() — StockService::stockOut() throws rather
    | than let stock go negative, and that throw would abort the whole run
    | mid-order. These pools mirror the real stock_quantity columns and are
    | decremented in lockstep with every line this seeder writes.
    */

    /** @param  Collection<int, Product>  $products */
    private function seedStockPools(Collection $products): void
    {
        foreach ($products as $product) {
            $this->productStock[$product->id] = (int) $product->stock_quantity;
            foreach ($product->variants as $variant) {
                $this->variantStock[$variant->id] = (int) $variant->stock_quantity;
            }
        }
    }

    /**
     * One to three line items, each a distinct product+variant with stock
     * still available in the pools above.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array{product: Product, variant: ProductVariant, qty: int, unit: float, total: float}>
     */
    private function pickLines(Collection $products, int $seed): array
    {
        $count = 1 + ($seed % 3);
        $lines = [];
        $usedVariantIds = [];

        for ($k = 0; $k < $count; $k++) {
            $line = $this->pickLine($products, $seed + $k, $usedVariantIds);
            if ($line === null) {
                continue;
            }
            $usedVariantIds[] = $line['variant']->id;
            $lines[] = $line;
        }

        return $lines;
    }

    private function pickLine(Collection $products, int $seed, array $usedVariantIds): ?array
    {
        $candidates = $products
            ->filter(fn (Product $p) => ($this->productStock[$p->id] ?? 0) > 0)
            ->values();

        for ($attempt = 0; $attempt < 15 && $candidates->isNotEmpty(); $attempt++) {
            $product = $candidates[($seed + $attempt) % $candidates->count()];

            $variant = $product->variants->first(fn ($v) => ($this->variantStock[$v->id] ?? 0) > 0
                && ! in_array($v->id, $usedVariantIds, true));

            if ($variant === null) {
                continue;
            }

            $available = min($this->productStock[$product->id], $this->variantStock[$variant->id]);
            $qty = min($available, 1 + ($seed % 2));

            $this->productStock[$product->id] -= $qty;
            $this->variantStock[$variant->id] -= $qty;

            $unit = (float) $variant->price;

            return [
                'product' => $product,
                'variant' => $variant,
                'qty' => $qty,
                'unit' => $unit,
                'total' => round($qty * $unit, 2),
            ];
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Coupons
    |--------------------------------------------------------------------------
    */

    /**
     * The same gate checkout applies: `Coupon::isValid()` (active, within its
     * window, under its usage cap) plus `calculateDiscount()` returning
     * something greater than zero for this subtotal (which folds in
     * `min_order_amount`). Coupons already used earlier in this same run have
     * an updated `used_count` in memory via `incrementUsage()`, so a capped
     * coupon correctly stops being offered partway through the loop —
     * exactly like a real shopper would see once the cap is hit.
     *
     * @param  Collection<int, Coupon>  $coupons
     */
    private function pickCoupon(Collection $coupons, float $subtotal): ?Coupon
    {
        $eligible = $coupons->filter(fn (Coupon $c) => $c->isValid() && $c->calculateDiscount($subtotal) > 0);

        return $eligible->isEmpty() ? null : $eligible->random();
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers (mirrors DemoOrderSeeder)
    |--------------------------------------------------------------------------
    */

    private function paymentStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'pending',
            'cancelled' => 'failed',
            default => 'paid',
        };
    }

    private function shippingStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'shipped',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    /** @return array<string, mixed> */
    private function lifecycleTimestamps(string $status, $placedAt): array
    {
        $stamps = [];

        if (! in_array($status, ['pending', 'cancelled'], true)) {
            $stamps['paid_at'] = (clone $placedAt)->addMinutes(12);
        }
        if (in_array($status, ['processing', 'completed'], true)) {
            $stamps['shipped_at'] = (clone $placedAt)->addDay();
        }
        if ($status === 'completed') {
            $stamps['delivered_at'] = (clone $placedAt)->addDays(3);
        }
        if ($status === 'cancelled') {
            $stamps['cancelled_at'] = (clone $placedAt)->addHours(5);
        }

        return $stamps;
    }

    /** @param  array<string, array{uses: int, discount: float}>  $couponRunStats */
    private function report(int $created, int $ordersWithCoupon, float $totalDiscount, array $couponRunStats): void
    {
        $this->command->info("Created {$created} orders across ".count(self::STATUSES).' statuses.');
        $this->command->info("{$ordersWithCoupon} orders applied a coupon, for \${$totalDiscount} of discount total.");

        if (empty($couponRunStats)) {
            $this->command->warn('No coupon was ever valid for the subtotals generated — check the coupons table (is_active / expires_at / usage_limit).');

            return;
        }

        foreach ($couponRunStats as $code => $stats) {
            $coupon = Coupon::where('code', $code)->first();
            $this->command->line(sprintf(
                '  %-16s used %d time(s) this run, $%.2f discounted — used_count now %d/%s',
                $code,
                $stats['uses'],
                $stats['discount'],
                $coupon->used_count,
                $coupon->usage_limit ?? '∞',
            ));
        }
    }
}
