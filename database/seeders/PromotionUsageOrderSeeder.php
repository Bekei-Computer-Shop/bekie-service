<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductPromotionService;
use App\Services\TaxCalculator;
use Database\Seeders\Support\DemoData;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Hand-picked orders that put the promotions into the states the storefront
 * and admin need to show, instead of leaving it to chance:
 *
 *  - RAMFLASH24: three orders inside its two-day window use all three of its
 *    uses, so it reads as both expired and exhausted;
 *  - VIPWORK100: three VIP orders use it up; a fourth VIP order a week later
 *    gets no VIP workstation discount because it is exhausted;
 *  - STORAGEDEAL5: two orders exhaust a running product promotion;
 *  - RTX4070LAUNCH: one Gamers Club member uses their single allowed use;
 *  - STUDENTLAP18 and B2BBULK12: group promotions applied to members;
 *  - WELCOME10: a typed code stacking with a product promotion.
 *
 * Runs before the other demo order seeders, which leave the first three
 * codes alone (PromotionSeeder::SCENARIO_CODES), so the outcome is the same
 * on every run. Every discount is priced by DemoOrderBuilder; nothing here
 * sets an amount by hand.
 *
 * Re-running is a no-op if its orders already exist; `php artisan
 * demo:reset` rebuilds them.
 *
 * Usage: php artisan db:seed --class=PromotionUsageOrderSeeder
 */
class PromotionUsageOrderSeeder extends Seeder
{
    public const TAG = 'promotion-usage-seeder';

    /**
     * order suffix, buyer (CustomerGroupSeeder email), days ago, status,
     * payment method, lines [variant SKU, quantity], typed code.
     */
    private const ORDERS = [
        ['RAM-1', 'shopper.borey26@bekie.test', 4, 'completed', 'aba_payway', [['COR-VENG-DDR5-32GB-2X16GB-5600MTS', 2]], null],
        ['RAM-2', 'shopper.rotha28@bekie.test', 4, 'completed', 'cod', [['COR-VENG-DDR5-32GB-2X16GB-5600MTS', 2]], null],
        ['RAM-3', 'shopper.sopheak30@bekie.test', 4, 'processing', 'aba_payway', [['GSK-RIPS5-32GB-2X16GB-MATTE-BLACK', 2]], null],
        ['VIP-1', 'shopper.narin1@bekie.test', 20, 'completed', 'aba_payway', [['AS-STRIXG16CH-RTX-4070-32GB-1TB', 1]], null],
        ['VIP-2', 'shopper.sophea2@bekie.test', 19, 'completed', 'cod', [['AS-STRIXG16CH-RTX-4070-32GB-1TB', 1]], null],
        ['VIP-3', 'shopper.ratana4@bekie.test', 18, 'completed', 'aba_payway', [['DEL-XPS8960-CORE-I7-14700-32GB-2TB', 1]], null],
        ['VIP-4', 'shopper.mony3@bekie.test', 11, 'processing', 'aba_payway', [['AS-STRIXG16CH-RTX-4070-32GB-1TB', 1]], null],
        ['STO-1', 'shopper.pisey5@bekie.test', 7, 'completed', 'cod', [['SEA-BARRA-2TB', 1]], null],
        ['STO-2', 'shopper.vanna6@bekie.test', 6, 'completed', 'aba_payway', [['SEA-BARRA-4TB', 1]], null],
        ['RTX-1', 'shopper.rachana13@bekie.test', 2, 'processing', 'aba_payway', [['GIG-4070TIS-GOC-STD', 1]], null],
        ['STU-1', 'shopper.chanthy7@bekie.test', 6, 'completed', 'cod', [['AS-VIVO15-16GB-1TB-COOL-SILVER', 1]], null],
        // Printers carry no product promotion, so the group discount is the best one.
        ['B2B-1', 'shopper.sitha18@bekie.test', 5, 'completed', 'aba_payway', [['BRO-HLL2460DW-STD', 8]], null],
        ['WEL-1', 'shopper.seyha8@bekie.test', 1, 'pending', 'cod', [['AOC-24G2SP-STD', 1], ['LOG-MXA3S-GRAPHITE', 1]], 'WELCOME10'],
    ];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $existing = Order::query()->withTrashed()->where('metadata->seeded_by', self::TAG)->count();
        if ($existing > 0) {
            $this->command?->warn("Skipped: {$existing} promotion scenario orders already exist. Rebuild them with: php artisan demo:reset");

            return;
        }

        $builder = new DemoOrderBuilder(app(ProductPromotionService::class), app(TaxCalculator::class), PromotionSeeder::codes());

        foreach (self::ORDERS as [$suffix, $email, $daysAgo, $status, $method, $lines, $code]) {
            $builder->create([
                'tag' => self::TAG,
                'order_number' => 'ORD-PU-'.$suffix,
                'customer' => $this->buyer($email),
                'placed_at' => now()->subDays($daysAgo)->setTime(11, 30),
                'status' => $status,
                'payment_method' => $method,
                'lines' => array_map(fn (array $line): array => [
                    'variant' => $this->variant($line[0]),
                    'quantity' => $line[1],
                ], $lines),
                'coupon' => $code !== null ? Coupon::query()->where('code', $code)->firstOrFail() : null,
            ]);
        }

        $this->command?->info(sprintf('Created %d promotion scenario orders.', count(self::ORDERS)));
    }

    private function buyer(string $email): User
    {
        return User::query()->where('email', $email)->first()
            ?? throw new RuntimeException("Scenario buyer {$email} is missing — run CustomerGroupSeeder first.");
    }

    private function variant(string $sku): ProductVariant
    {
        return ProductVariant::query()->with('product')->where('sku', $sku)->first()
            ?? throw new RuntimeException("Scenario variant {$sku} is missing — run DemoCatalogSeeder first.");
    }
}
