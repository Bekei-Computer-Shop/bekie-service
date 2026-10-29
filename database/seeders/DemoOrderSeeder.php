<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Api\Admin\V1\StoreOrderRequest;
use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductPromotionService;
use App\Services\TaxCalculator;
use Database\Seeders\Support\DemoData;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Sample orders for developing/demoing the admin Orders screen.
 *
 * The buyers are the shoppers CustomerSeeder and CustomerGroupSeeder create —
 * this seeder makes no users of its own. Every non-admin user is a customer as
 * far as CustomerController is concerned, so a second set of buyers would show
 * up on the Customers screen as duplicates of the ones already there.
 * Run those (and PromotionSeeder) first; DatabaseSeeder already orders them so.
 *
 * Every row it creates is tagged with metadata->seeded_by so demo data can be
 * told apart from real orders; `php artisan demo:reset` removes and rebuilds it.
 * Orders go through DemoOrderBuilder, so totals, promotion discounts, usage
 * counters, payments and serial numbers match what checkout would produce.
 *
 * Re-running is safe: if tagged orders already exist it reports and does
 * nothing rather than duplicating them.
 *
 * Usage: php artisan db:seed --class=DemoOrderSeeder
 */
class DemoOrderSeeder extends Seeder
{
    public const TAG = 'demo-order-seeder';

    private const ORDER_COUNT = 90;

    /** The canonical statuses — see UpdateOrderRequest::STATUSES. */
    private const STATUSES = UpdateOrderRequest::STATUSES;

    /** The methods checkout accepts — see StoreOrderRequest::PAYMENT_METHODS. */
    private const METHODS = StoreOrderRequest::PAYMENT_METHODS;

    /** Every few orders the shopper types a code at checkout as well. */
    private const CODES = [6 => 'WELCOME10', 9 => 'BIGSPEND50', 13 => 'OLDCODE20'];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $this->warnAboutLegacyBuyers();

        // withTrashed: orders soft-delete, and a soft-deleted row still holds
        // its order_number in the unique index. Counting only live rows would
        // report a clean slate and then fail on the first insert.
        $existing = Order::query()->withTrashed()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command->warn("Skipped: {$existing} seeded demo orders already exist (including any soft-deleted ones).");
            $this->command->line('Regenerate them, with their usage counters, payments and serials: php artisan demo:reset');

            return;
        }

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('variants', fn ($query) => $query->where('is_active', true))
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sku')
            ->get();
        if ($products->isEmpty()) {
            $this->command->error('No products found — run ProductCatalogSeeder first.');

            return;
        }

        $customers = $this->customers();
        if ($customers->isEmpty()) {
            $this->command->error('No seeded customers found — run CustomerSeeder first.');

            return;
        }

        $builder = new DemoOrderBuilder(app(ProductPromotionService::class), app(TaxCalculator::class), PromotionSeeder::codes());
        $codes = Coupon::query()->whereIn('code', self::CODES)->get()->keyBy('code');
        $created = 0;

        for ($i = 1; $i <= self::ORDER_COUNT; $i++) {
            $code = collect(self::CODES)->first(fn (string $code, int $every): bool => $i % $every === 0);

            // Priced by DemoOrderBuilder as checkout would have on that day.
            // Spread over a year so the reports' month and year filters have data.
            $builder->create([
                'tag' => self::TAG,
                'order_number' => 'ORD-'.str_pad((string) (1000 + $i), 6, '0', STR_PAD_LEFT),
                'customer' => $customers[$i % $customers->count()],
                'placed_at' => now()->subDays($i * 4)->setTime(8 + ($i % 10), ($i * 13) % 60),
                'status' => self::STATUSES[$i % count(self::STATUSES)],
                'payment_method' => self::METHODS[$i % count(self::METHODS)],
                'lines' => $this->lines($products, $i),
                'coupon' => $code !== null ? $codes->get($code) : null,
                'skip_promotions' => PromotionSeeder::SCENARIO_CODES,
            ]);

            $created++;
        }

        $this->command->info("Created {$created} demo orders across ".count(self::STATUSES).' statuses.');
    }

    /**
     * Earlier runs of this seeder made their own `demo.*@bekie.test` buyers,
     * duplicating CustomerSeeder's shoppers on the admin Customers screen.
     * Nothing here creates them any more, but a database seeded before that
     * change still holds them, so point them out rather than leaving the
     * duplicates to be puzzled over.
     */
    private function warnAboutLegacyBuyers(): void
    {
        $legacy = User::query()->where('email', 'like', 'demo.%@bekie.test')->count();
        if ($legacy === 0) {
            return;
        }

        $this->command->warn("{$legacy} buyers from an older run of this seeder are still on the Customers screen.");
        $this->command->line('They are no longer created. Remove them and the orders they hold with:');
        $this->command->line("  delete from order_items where order_id in (select id from orders where user_id in (select id from users where email like 'demo.%@bekie.test'));");
        $this->command->line("  delete from orders where user_id in (select id from users where email like 'demo.%@bekie.test');");
        $this->command->line("  delete from users where email like 'demo.%@bekie.test';");
    }

    /**
     * The buyers: CustomerSeeder's shoppers, then CustomerGroupSeeder's, in
     * seed order.
     *
     * Only the ones CustomerSeeder already gives order history to — it leaves
     * one customer at zero orders on purpose, and adding demo orders for them
     * would erase that empty-spend row from the Customers list. The grouped
     * shoppers bring group promotions and the VIP discount into the orders.
     */
    private function customers()
    {
        $emails = [...CustomerSeeder::buyerEmails(), ...CustomerGroupSeeder::buyerEmails()];

        return User::query()
            ->whereIn('email', $emails)
            ->get()
            // Ordered by the seed list rather than by id, so which buyer gets
            // which order stays the same between runs.
            ->sortBy(fn (User $user) => array_search($user->email, $emails, true))
            ->values();
    }

    /**
     * One to three line items, each one of the product's own variants.
     *
     * @return list<array{variant: ProductVariant, quantity: int}>
     */
    private function lines(Collection $products, int $i): array
    {
        $lines = [];

        for ($k = 0; $k < 1 + ($i % 3); $k++) {
            // A stride walks the whole catalog instead of the first few rows.
            $product = $products[($i * 7 + $k * 13) % $products->count()];

            $lines[] = [
                'variant' => $product->variants[($i + $k) % $product->variants->count()],
                'quantity' => 1 + (($i + $k) % 2),
            ];
        }

        return $lines;
    }
}
