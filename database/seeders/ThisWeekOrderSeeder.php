<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 15-20 orders per day for every day of the current calendar week that has
 * actually happened yet (Monday..today) — the seeded demo data
 * (DemoOrderSeeder) all predates the current week, which is why the admin
 * dashboard's "Orders This Week" chart otherwise shows zero for every day.
 *
 * Every foreign key an order (and its line items) can carry — user, address,
 * coupon, product, variant — is populated rather than left null, unlike
 * DemoOrderSeeder which leaves address_id/coupon_id empty.
 *
 * Tagged the same way as DemoOrderSeeder so it can be told apart and cleaned
 * up on its own. Re-running is a no-op if a tagged batch already exists.
 *
 * Wired into DatabaseSeeder (after ProductCatalogSeeder, CustomerSeeder and
 * PromotionSeeder, which supply the products/addressed customers/coupons
 * this seeder needs) so every full reseed keeps the current week populated —
 * this class being left standalone before is exactly why the dashboard chart
 * went back to nearly empty after any `migrate:fresh --seed`.
 *
 * Usage: php artisan db:seed --class=ThisWeekOrderSeeder
 */
class ThisWeekOrderSeeder extends Seeder
{
    private const TAG = 'this-week-order-seeder';

    /** Orders per day, Monday..Sunday. Every value is in the 15-20 range. */
    private const DAILY_COUNTS = [18, 15, 20, 16, 19, 17, 20];

    /** The canonical statuses — see UpdateOrderRequest::STATUSES. */
    private const STATUSES = UpdateOrderRequest::STATUSES;

    private const METHODS = ['cod', 'bank_transfer', 'stripe', 'paypal'];

    private const PROVIDERS = ['J&T Express', 'DHL Express', 'GrabExpress', 'Virak Buntham'];

    public function run(): void
    {
        // withTrashed: orders soft-delete, and a soft-deleted row still holds
        // its order_number in the unique index.
        $existing = Order::query()->withTrashed()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command->warn("Skipped: {$existing} seeded this-week orders already exist.");
            $this->command->line('Remove them for a fresh set:');
            $this->command->line("  delete from order_items where order_id in (select id from orders where metadata->>'seeded_by' = '".self::TAG."');");
            $this->command->line("  delete from orders where metadata->>'seeded_by' = '".self::TAG."';");

            return;
        }

        $products = Product::query()->active()->get();
        if ($products->isEmpty()) {
            $this->command->error('No active products found — run ProductCatalogSeeder first.');

            return;
        }

        $customers = User::query()->where('is_admin', false)->whereHas('addresses')->with('addresses')->get();
        if ($customers->isEmpty()) {
            $this->command->error('No customers with a saved address found — run CustomerSeeder first.');

            return;
        }

        $coupons = Coupon::query()->get();
        if ($coupons->isEmpty()) {
            $this->command->error('No coupons found — run PromotionSeeder first.');

            return;
        }

        $weekStart = now()->startOfWeek();

        // Orders can't be created in the future: cap the spread at today
        // rather than running it through to Sunday.
        $maxDayOffset = min(6, (int) $weekStart->copy()->startOfDay()->diffInDays(now()->startOfDay()));

        $created = 0;
        $i = 0;

        for ($dayOffset = 0; $dayOffset <= $maxDayOffset; $dayOffset++) {
            $dayCount = self::DAILY_COUNTS[$dayOffset];

            for ($n = 1; $n <= $dayCount; $n++) {
                $i++;

                $customer = $customers[($i - 1) % $customers->count()];
                $address = $customer->addresses->first();
                $coupon = $coupons[($i - 1) % $coupons->count()];
                $status = self::STATUSES[$i % count(self::STATUSES)];
                $method = self::METHODS[$i % count(self::METHODS)];

                // Spread across the day at a different time each time so the
                // dashboard's per-day bars vary instead of clustering.
                $placedAt = $weekStart->copy()
                    ->addDays($dayOffset)
                    ->setTime(8 + ($n % 11), ($i * 17) % 60, ($i * 7) % 60);

                $lines = $this->lines($products, $i);
                $subtotal = round(array_sum(array_column($lines, 'total')), 2);
                $discount = $coupon->calculateDiscount($subtotal);
                $tax = round(($subtotal - $discount) * 0.10, 2);
                $shipping = $subtotal > 1000 ? 0.0 : 7.50;
                $grandTotal = round($subtotal - $discount + $tax + $shipping, 2);

                $order = Order::create([
                    'order_number' => sprintf('ORD-TW-%06d', $i),
                    'user_id' => $customer->id,
                    'address_id' => $address->id,
                    'status' => $status,
                    'notes' => "Seeded this-week order #{$i} for the dashboard chart.",
                    'currency' => 'USD',
                    'payment_method' => $method,
                    'payment_status' => $this->paymentStatus($status),
                    'transaction_id' => 'txn_'.uniqid(),
                    'subtotal' => $subtotal,
                    'discount_total' => $discount,
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'tax_total' => $tax,
                    'shipping_total' => $shipping,
                    'grand_total' => $grandTotal,
                    'shipping_status' => $this->shippingStatus($status),
                    'tracking_number' => 'TRK'.mt_rand(10000000, 99999999),
                    'shipping_provider' => self::PROVIDERS[$i % count(self::PROVIDERS)],
                    'customer_snapshot' => [
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ],
                    'address_snapshot' => $address->only([
                        'full_name', 'phone', 'address_line_1', 'address_line_2',
                        'city', 'state', 'postal_code', 'country',
                    ]),
                    'metadata' => ['seeded_by' => self::TAG],
                ]);

                // created_at is not fillable, so the placement date is forced after.
                $order->forceFill(array_merge(
                    ['created_at' => $placedAt, 'updated_at' => $placedAt],
                    $this->lifecycleTimestamps($status, $placedAt),
                ))->saveQuietly();

                foreach ($lines as $line) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'product_variant_id' => $line['variant']->id,
                        'quantity' => $line['qty'],
                        'unit_price' => $line['unit'],
                        'sale_price' => $line['variant']->sale_price,
                        'cost_price' => $line['variant']->cost_price,
                        'subtotal' => $line['total'],
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $line['total'],
                        'product_name' => $line['product']->name,
                        'product_sku' => $line['variant']->sku,
                        'variant_name' => $line['variant']->name,
                        'variant_attributes' => $line['variant']->attributes,
                        'status' => $this->itemStatus($status),
                    ]);
                }

                $created++;
            }
        }

        $this->command->info("Created {$created} orders dated {$weekStart->toDateString()} through ".now()->toDateString().'.');
    }

    /**
     * One to three line items, each tied to one of the product's own variants
     * so product_variant_id is never left null.
     *
     * @return array<int, array{product: Product, variant: ProductVariant, qty: int, unit: float, total: float}>
     */
    private function lines(Collection $products, int $i): array
    {
        $count = 1 + ($i % 3);
        $lines = [];

        for ($k = 0; $k < $count; $k++) {
            $product = $products[($i + $k) % $products->count()];
            $variants = ProductVariant::query()->where('product_id', $product->id)->get();
            $variant = $variants[($i + $k) % $variants->count()];
            $qty = 1 + (($i + $k) % 2);
            $unit = (float) ($variant->price ?? $product->price ?? 99.00);

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'qty' => $qty,
                'unit' => $unit,
                'total' => round($qty * $unit, 2),
            ];
        }

        return $lines;
    }

    private function paymentStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'pending',
            'cancelled' => 'failed',
            default => 'paid',
        };
    }

    /**
     * shipping_status is its own column with its own vocabulary, so it is
     * derived from the order status rather than mirroring it.
     */
    private function shippingStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'shipped',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function itemStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'shipped',
            'completed' => 'delivered',
            'cancelled' => 'returned',
            default => 'pending',
        };
    }

    /**
     * Keeps paid_at/shipped_at/... consistent with the order status.
     *
     * @return array<string, mixed>
     */
    private function lifecycleTimestamps(string $status, $placedAt): array
    {
        $stamps = [];

        if (! in_array($status, ['pending', 'cancelled'], true)) {
            $stamps['paid_at'] = (clone $placedAt)->addMinutes(12);
        }
        if (in_array($status, ['processing', 'completed'], true)) {
            $stamps['shipped_at'] = (clone $placedAt)->addHours(6);
        }
        if ($status === 'completed') {
            $stamps['delivered_at'] = (clone $placedAt)->addDay();
        }
        if ($status === 'cancelled') {
            $stamps['cancelled_at'] = (clone $placedAt)->addHours(2);
        }

        return $stamps;
    }
}
