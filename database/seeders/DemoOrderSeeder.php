<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample orders for developing/demoing the admin Orders screen.
 *
 * Every row it creates is tagged with metadata->seeded_by so demo data can be
 * told apart from real orders and removed again (see the class docblock note at
 * the bottom of this file for the delete statement).
 *
 * Re-running is safe: if tagged orders already exist it reports and does
 * nothing rather than duplicating them.
 *
 * Usage: php artisan db:seed --class=DemoOrderSeeder
 */
class DemoOrderSeeder extends Seeder
{
    private const TAG = 'demo-order-seeder';

    private const ORDER_COUNT = 30;

    /** The canonical statuses — see UpdateOrderRequest::STATUSES. */
    private const STATUSES = UpdateOrderRequest::STATUSES;

    private const METHODS = ['cod', 'bank_transfer', 'stripe', 'paypal'];

    private const CUSTOMERS = [
        ['Sokha', 'Chan'],
        ['Dara', 'Lim'],
        ['Vichea', 'Pen'],
        ['Bopha', 'Sok'],
        ['Rithy', 'Norng'],
        ['Kanha', 'Meas'],
        ['Sovann', 'Ly'],
        ['Chenda', 'Kim'],
    ];

    public function run(): void
    {
        $existing = Order::query()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command->warn("Skipped: {$existing} seeded demo orders already exist.");
            $this->command->line('Remove them first if you want a fresh set:');
            $this->command->line("  delete from order_items where order_id in (select id from orders where metadata->>'seeded_by' = '".self::TAG."');");
            $this->command->line("  delete from orders where metadata->>'seeded_by' = '".self::TAG."';");

            return;
        }

        $products = Product::query()->take(8)->get();
        if ($products->isEmpty()) {
            $this->command->error('No products found — run ProductSeeder first.');

            return;
        }

        $customers = $this->customers();
        $created = 0;

        for ($i = 1; $i <= self::ORDER_COUNT; $i++) {
            $customer = $customers[$i % $customers->count()];
            $status = self::STATUSES[$i % count(self::STATUSES)];
            $method = self::METHODS[$i % count(self::METHODS)];

            // Spread over roughly five months so the month filter has options.
            $placedAt = now()->subDays($i * 4)->setTime(8 + ($i % 10), ($i * 13) % 60);

            $lines = $this->lines($products, $i);
            $subtotal = round(array_sum(array_column($lines, 'total')), 2);
            $discount = $i % 5 === 0 ? round($subtotal * 0.10, 2) : 0.0;
            $tax = round(($subtotal - $discount) * 0.10, 2);
            $shipping = $subtotal > 1000 ? 0.0 : 7.50;
            $grandTotal = round($subtotal - $discount + $tax + $shipping, 2);

            $order = Order::create([
                'order_number' => 'ORD-'.str_pad((string) (1000 + $i), 6, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'status' => $status,
                'currency' => 'USD',
                'payment_method' => $method,
                'payment_status' => $this->paymentStatus($status),
                'transaction_id' => in_array($method, ['stripe', 'paypal'], true) ? 'txn_'.uniqid() : null,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'shipping_total' => $shipping,
                'grand_total' => $grandTotal,
                'shipping_status' => $this->shippingStatus($status),
                'tracking_number' => in_array($status, ['processing', 'completed'], true) ? 'TRK'.mt_rand(10000000, 99999999) : null,
                'shipping_provider' => in_array($status, ['processing', 'completed'], true) ? 'J&T Express' : null,
                // NOTE: no `notes` here. OrderResource and both order requests
                // reference a `notes` field, but the orders table has no such
                // column and it is not in Order::$fillable — the app silently
                // discards it. Seeding would hard-fail because db:seed unguards
                // models. Flagged separately; nothing to write here yet.
                'customer_snapshot' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
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
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unit'],
                    'subtotal' => $line['total'],
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $line['total'],
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                ]);
            }

            $created++;
        }

        $this->command->info("Created {$created} demo orders across ".count(self::STATUSES).' statuses.');
    }

    /**
     * Demo buyers, reused on re-runs. Emails use .test so they can never collide
     * with a real address.
     */
    private function customers()
    {
        return collect(self::CUSTOMERS)->map(function (array $person, int $index) {
            [$first, $last] = $person;

            $user = User::firstOrCreate(
                ['email' => 'demo.'.strtolower($first).$index.'@bekie.test'],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'username' => 'demo_'.strtolower($first).$index,
                    'phone' => '+85512'.str_pad((string) (100000 + $index), 6, '0'),
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'is_active' => true,
                ]
            );

            $this->ensureAddress($user, $index);

            return $user;
        })->values();
    }

    /** Street addresses the demo customers deliver to. */
    private const STREETS = [
        ['12 Street 240', 'Chamkarmon', 'Phnom Penh', '12301'],
        ['88 Norodom Blvd', 'Daun Penh', 'Phnom Penh', '12207'],
        ['45 Street 271', 'Toul Kork', 'Phnom Penh', '12151'],
        ['7 Sivutha Blvd', 'Svay Dangkum', 'Siem Reap', '17252'],
        ['203 Ekareach St', 'Buon', 'Sihanoukville', '18000'],
        ['31 Street 105', 'Prampi Makara', 'Phnom Penh', '12253'],
        ['9 Mondul 2', 'Svay Dangkum', 'Siem Reap', '17259'],
        ['150 Street 63', 'Boeung Keng Kang', 'Phnom Penh', '12302'],
    ];

    /**
     * Gives each demo customer one default shipping address, so the order forms
     * and printed orders have a delivery address to show. Idempotent.
     */
    private function ensureAddress(User $user, int $index): void
    {
        if ($user->addresses()->exists()) {
            return;
        }

        [$line1, $state, $city, $postal] = self::STREETS[$index % count(self::STREETS)];

        $user->addresses()->create([
            'type' => 'shipping',
            'label' => 'Home',
            'full_name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'address_line_1' => $line1,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal,
            'country' => 'Cambodia',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * One to three line items built from real product prices.
     *
     * @return array<int, array{product: Product, qty: int, unit: float, total: float}>
     */
    private function lines($products, int $i): array
    {
        $count = 1 + ($i % 3);
        $lines = [];

        for ($k = 0; $k < $count; $k++) {
            $product = $products[($i + $k) % $products->count()];
            $qty = 1 + (($i + $k) % 2);
            $unit = (float) ($product->price ?? 99.00);
            $lines[] = [
                'product' => $product,
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
}
