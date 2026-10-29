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
 * 15-20 orders per day for every day of the current calendar week that has
 * actually happened yet (Monday..today) — the seeded demo data
 * (DemoOrderSeeder) all predates the current week, which is why the admin
 * dashboard's "Orders This Week" chart otherwise shows zero for every day.
 *
 * Every foreign key an order (and its line items) can carry — user, address,
 * product, variant, and the coupon when a code was used — is populated.
 * Orders go through DemoOrderBuilder, so totals, promotion discounts, usage
 * counters, payments and serial numbers match what checkout would produce.
 *
 * The buyers are the demo shoppers only (CustomerSeeder and
 * CustomerGroupSeeder), never a real customer who happens to have an address.
 *
 * Tagged the same way as DemoOrderSeeder so it can be told apart and rebuilt
 * with `php artisan demo:reset`. Re-running is a no-op if a tagged batch
 * already exists.
 *
 * Wired into DatabaseSeeder after the catalog, customer and promotion seeders
 * so every full reseed keeps the current week populated — this class being
 * left standalone before is exactly why the dashboard chart went back to
 * nearly empty after any `migrate:fresh --seed`.
 *
 * Usage: php artisan db:seed --class=ThisWeekOrderSeeder
 */
class ThisWeekOrderSeeder extends Seeder
{
    public const TAG = 'this-week-order-seeder';

    /** Orders per day, Monday..Sunday. Every value is in the 15-20 range. */
    private const DAILY_COUNTS = [18, 15, 20, 16, 19, 17, 20];

    /** The canonical statuses — see UpdateOrderRequest::STATUSES. */
    private const STATUSES = UpdateOrderRequest::STATUSES;

    /** The methods checkout accepts — see StoreOrderRequest::PAYMENT_METHODS. */
    private const METHODS = StoreOrderRequest::PAYMENT_METHODS;

    /** Every few orders the shopper types a code at checkout as well. */
    private const CODES = [5 => 'WELCOME10', 8 => 'BIGSPEND50'];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        // withTrashed: orders soft-delete, and a soft-deleted row still holds
        // its order_number in the unique index.
        $existing = Order::query()->withTrashed()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command->warn("Skipped: {$existing} seeded this-week orders already exist.");
            $this->command->line('Regenerate them, with their usage counters, payments and serials: php artisan demo:reset');

            return;
        }

        $products = Product::query()
            ->active()
            ->whereHas('variants', fn ($query) => $query->where('is_active', true))
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sku')
            ->get();
        if ($products->isEmpty()) {
            $this->command->error('No active products found — run ProductCatalogSeeder first.');

            return;
        }

        $emails = [...CustomerSeeder::buyerEmails(), ...CustomerGroupSeeder::buyerEmails()];
        $customers = User::query()
            ->whereIn('email', $emails)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->whereHas('addresses')
            ->get()
            ->sortBy(fn (User $user) => array_search($user->email, $emails, true))
            ->values();
        if ($customers->isEmpty()) {
            $this->command->error('No demo customers with a saved address found — run CustomerSeeder first.');

            return;
        }

        $builder = new DemoOrderBuilder(app(ProductPromotionService::class), app(TaxCalculator::class), PromotionSeeder::codes());
        $codes = Coupon::query()->whereIn('code', self::CODES)->get()->keyBy('code');
        $weekStart = now()->startOfWeek();

        // Orders can't be created in the future: cap the spread at today
        // rather than running it through to Sunday.
        $maxDayOffset = min(6, (int) $weekStart->copy()->startOfDay()->diffInDays(now()->startOfDay()));

        $created = 0;
        $i = 0;

        for ($dayOffset = 0; $dayOffset <= $maxDayOffset; $dayOffset++) {
            for ($n = 1; $n <= self::DAILY_COUNTS[$dayOffset]; $n++) {
                $i++;

                // Spread across the day at a different time each time so the
                // dashboard's per-day bars vary instead of clustering — but
                // never later than now, today.
                $placedAt = $weekStart->copy()
                    ->addDays($dayOffset)
                    ->setTime(8 + ($n % 11), ($i * 17) % 60, ($i * 7) % 60);
                if ($placedAt->isFuture()) {
                    $placedAt = now()->subMinutes($n * 3);
                }

                $code = collect(self::CODES)->first(fn (string $code, int $every): bool => $i % $every === 0);

                $builder->create([
                    'tag' => self::TAG,
                    'order_number' => sprintf('ORD-TW-%06d', $i),
                    'customer' => $customers[($i - 1) % $customers->count()],
                    'placed_at' => $placedAt,
                    'status' => self::STATUSES[$i % count(self::STATUSES)],
                    'payment_method' => self::METHODS[$i % count(self::METHODS)],
                    'lines' => $this->lines($products, $i),
                    'coupon' => $code !== null ? $codes->get($code) : null,
                    'skip_promotions' => PromotionSeeder::SCENARIO_CODES,
                    'notes' => "Seeded this-week order #{$i} for the dashboard chart.",
                ]);

                $created++;
            }
        }

        $this->command->info("Created {$created} orders dated {$weekStart->toDateString()} through ".now()->toDateString().'.');
    }

    /**
     * One to three line items, each tied to one of the product's own variants
     * so product_variant_id is never left null.
     *
     * @return list<array{variant: ProductVariant, quantity: int}>
     */
    private function lines(Collection $products, int $i): array
    {
        $lines = [];

        for ($k = 0; $k < 1 + ($i % 3); $k++) {
            $product = $products[($i * 5 + $k * 11) % $products->count()];

            $lines[] = [
                'variant' => $product->variants[($i + $k) % $product->variants->count()],
                'quantity' => 1 + (($i + $k) % 2),
            ];
        }

        return $lines;
    }
}
