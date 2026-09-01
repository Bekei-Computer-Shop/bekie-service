<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Resources\Api\Admin\V1\CustomerResource;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ten shopper accounts for developing/demoing the admin Customers screen.
 *
 * Each one gets a default shipping address and a run of completed orders, so
 * the list's "Total Spent" and "Completed Orders" columns show real aggregates
 * rather than zeroes — those two are summed from `orders`, not stored on the
 * user.
 *
 * Re-running is safe: users are matched on their `@bekie.test` email and orders
 * on `metadata->seeded_by`, so nothing is duplicated.
 *
 * Usage: php artisan db:seed --class=CustomerSeeder
 */
class CustomerSeeder extends Seeder
{
    private const TAG = 'customer-seeder';

    /**
     * first, last, phone suffix, status, [line 1, district, city, postcode],
     * and how many completed orders to build for them.
     *
     * The status mix is deliberate: the list has all three badges on screen,
     * and the two `inactive` rows cover both is_active=false paths through
     * CustomerResource::statusOf().
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:array{0:string,1:string,2:string,3:string},4:mixed,5:int}>
     */
    private const CUSTOMERS = [
        ['Sokha', 'Chan', '100001', 'vip', ['12 Street 240', 'Chamkarmon', 'Phnom Penh', '12301'], 6],
        ['Dara', 'Lim', '100002', 'active', ['88 Norodom Blvd', 'Daun Penh', 'Phnom Penh', '12207'], 3],
        ['Vichea', 'Pen', '100003', 'active', ['45 Street 271', 'Toul Kork', 'Phnom Penh', '12151'], 2],
        ['Bopha', 'Sok', '100004', 'vip', ['7 Sivutha Blvd', 'Svay Dangkum', 'Siem Reap', '17252'], 8],
        ['Rithy', 'Norng', '100005', 'inactive', ['203 Ekareach St', 'Buon', 'Sihanoukville', '18000'], 1],
        ['Kanha', 'Meas', '100006', 'active', ['31 Street 105', 'Prampi Makara', 'Phnom Penh', '12253'], 4],
        ['Sovann', 'Ly', '100007', 'active', ['9 Mondul 2', 'Svay Dangkum', 'Siem Reap', '17259'], 0],
        ['Chenda', 'Kim', '100008', 'inactive', ['150 Street 63', 'Boeung Keng Kang', 'Phnom Penh', '12302'], 2],
        ['Piseth', 'Heng', '100009', 'active', ['64 Street 310', 'Chamkarmon', 'Phnom Penh', '12306'], 5],
        ['Sreymom', 'Yun', '100010', 'vip', ['18 Preah Sihanouk Blvd', 'Tonle Bassac', 'Phnom Penh', '12301'], 7],
    ];

    /**
     * The email this seeder gives the shopper at `$index` in self::CUSTOMERS.
     *
     * Public so other seeders can attach their own data to these accounts
     * instead of minting a second set of shoppers — every non-admin user shows
     * up on the admin Customers screen, so duplicates are visible there.
     */
    public static function emailFor(string $first, int $index): string
    {
        return 'customer.'.strtolower($first).($index + 1).'@bekie.test';
    }

    /**
     * Every shopper this seeder creates, in seed order.
     *
     * @return list<string>
     */
    public static function shopperEmails(): array
    {
        return array_map(
            fn (array $row, int $index) => self::emailFor($row[0], $index),
            self::CUSTOMERS,
            array_keys(self::CUSTOMERS),
        );
    }

    /**
     * Only the shoppers this seeder already gives order history to.
     *
     * `Sovann Ly` is deliberately left at zero orders so the Customers list has
     * a row with no spend on it. Another seeder adding orders for everyone
     * would quietly erase that case, so it hands out this list instead.
     *
     * @return list<string>
     */
    public static function buyerEmails(): array
    {
        $emails = [];

        foreach (self::CUSTOMERS as $index => $row) {
            if ($row[5] > 0) {
                $emails[] = self::emailFor($row[0], $index);
            }
        }

        return $emails;
    }

    public function run(): void
    {
        $products = Product::query()->take(8)->get();
        if ($products->isEmpty()) {
            $this->command->warn('No products found — customers will be created without orders. Run ProductSeeder first for spend totals.');
        }

        $vipGroup = CustomerGroup::firstOrCreate(
            ['slug' => CustomerResource::VIP_GROUP_SLUG],
            ['name' => 'VIP', 'description' => 'High-value customers.', 'is_active' => true],
        );

        $created = 0;
        $orders = 0;

        foreach (self::CUSTOMERS as $index => [$first, $last, $phoneSuffix, $status, $address, $orderCount]) {
            $email = self::emailFor($first, $index);

            $customer = User::firstOrNew(['email' => $email]);
            $isNew = ! $customer->exists;

            $customer->fill([
                'first_name' => $first,
                'last_name' => $last,
                'username' => 'shopper_'.strtolower($first).($index + 1),
                // 92-prefixed to keep the seeded range clear of real numbers.
                'phone' => '+85592'.$phoneSuffix,
                'is_admin' => false,
                // `inactive` is modelled as a deactivated account, not a ban —
                // same rule CustomerController::applyStatus() writes.
                'is_active' => $status !== 'inactive',
            ]);

            if ($isNew) {
                $customer->password = Hash::make('password');
                $customer->email_verified_at = now()->subMonths(($index % 6) + 1);
            }

            $customer->save();

            // `user` is the storefront role; without it the account has none of
            // the client permissions a real shopper would hold.
            if (! $customer->hasRole('user')) {
                $customer->assignRole('user');
            }

            if ($status === 'vip') {
                $customer->customerGroups()->syncWithoutDetaching([$vipGroup->id]);
            } else {
                $customer->customerGroups()->detach($vipGroup->id);
            }

            $this->ensureAddress($customer, $address);

            if ($products->isNotEmpty()) {
                $orders += $this->ensureOrders($customer, $products, $orderCount, $index);
            }

            $created++;
        }

        $this->command->info("Seeded {$created} customers with {$orders} completed orders.");
        $this->command->line('Remove them again with:');
        $this->command->line("  delete from order_items where order_id in (select id from orders where metadata->>'seeded_by' = '".self::TAG."');");
        $this->command->line("  delete from orders where metadata->>'seeded_by' = '".self::TAG."';");
        $this->command->line("  delete from users where email like 'customer.%@bekie.test';");
    }

    /**
     * One default shipping address per customer, kept structured so the admin
     * screen shows a full line — see CustomerResource::formatAddress().
     *
     * @param  array{0:string,1:string,2:string,3:string}  $address
     */
    private function ensureAddress(User $customer, array $address): void
    {
        if ($customer->addresses()->exists()) {
            return;
        }

        [$line1, $district, $city, $postal] = $address;

        $customer->addresses()->create([
            'type' => 'shipping',
            'label' => 'Home',
            'full_name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address_line_1' => $line1,
            // The district goes on line 2 rather than in `state`, so
            // CustomerResource::formatAddress() reads street, district, city,
            // postcode in that order.
            'address_line_2' => $district,
            'city' => $city,
            'postal_code' => $postal,
            'country' => 'Cambodia',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Completed orders behind the customer's spend total.
     *
     * Only `completed` is used: CustomerController sums that status alone, so
     * anything else would leave the seeded totals looking wrong on screen.
     *
     * @return int the number of orders created on this run
     */
    private function ensureOrders(User $customer, $products, int $count, int $index): int
    {
        if ($count === 0) {
            return 0;
        }

        $existing = Order::query()
            ->where('user_id', $customer->id)
            ->where('metadata->seeded_by', self::TAG)
            ->count();

        if ($existing > 0) {
            return 0;
        }

        for ($n = 1; $n <= $count; $n++) {
            $placedAt = now()->subDays(($index * 7) + ($n * 11))->setTime(9 + ($n % 8), ($n * 17) % 60);

            $lines = $this->lines($products, $index + $n);
            $subtotal = round(array_sum(array_column($lines, 'total')), 2);
            $tax = round($subtotal * 0.10, 2);
            $shipping = $subtotal > 1000 ? 0.0 : 7.50;
            $grandTotal = round($subtotal + $tax + $shipping, 2);

            $order = Order::create([
                'order_number' => 'CUS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'status' => 'completed',
                'currency' => 'USD',
                'payment_method' => $n % 2 === 0 ? 'stripe' : 'cod',
                'payment_status' => 'paid',
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $tax,
                'shipping_total' => $shipping,
                'grand_total' => $grandTotal,
                'shipping_status' => 'delivered',
                'customer_snapshot' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'metadata' => ['seeded_by' => self::TAG],
            ]);

            // created_at is not fillable, so the placement date is forced after.
            $order->forceFill([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
                'paid_at' => (clone $placedAt)->addMinutes(9),
                'shipped_at' => (clone $placedAt)->addDay(),
                'delivered_at' => (clone $placedAt)->addDays(3),
            ])->saveQuietly();

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
        }

        return $count;
    }

    /**
     * One to three line items built from real product prices.
     *
     * @return array<int, array{product: Product, qty: int, unit: float, total: float}>
     */
    private function lines($products, int $seed): array
    {
        $lines = [];

        for ($k = 0; $k < 1 + ($seed % 3); $k++) {
            $product = $products[($seed + $k) % $products->count()];
            $qty = 1 + (($seed + $k) % 2);
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
}
