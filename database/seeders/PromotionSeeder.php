<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;
use RuntimeException;

class PromotionSeeder extends Seeder
{
    /**
     * Promotions with tight usage limits that PromotionUsageOrderSeeder fills
     * on purpose. Every other demo order leaves them alone, so they end up
     * exhausted by exactly the orders meant to exhaust them.
     */
    public const SCENARIO_CODES = ['RAMFLASH24', 'VIPWORK100', 'STORAGEDEAL5'];

    /** A product promotion must name this many products (inclusive). */
    private const MIN_PRODUCTS = 10;

    private const MAX_PRODUCTS = 20;

    /**
     * Seed the promotions for the admin "Promotions & Campaigns" screen and
     * the storefront discount display.
     *
     * The screen reads `Coupon` through PromotionController — not the older
     * `Promotion` model, which is no longer wired to any admin route. Column
     * names here follow the coupons table: the amount is `value`, the window
     * closes at `expires_at`, and `type` is constrained by a CHECK to exactly
     * 'percentage' or 'fixed'.
     *
     * Every promotion has exactly one target type, the rule
     * ValidatesPromotionTargets enforces: products (optionally refined by
     * variants), categories, or customer groups. The few with no target are
     * plain codes, which only apply when typed in at checkout. Targets are
     * restricted to active rows, so each promotion can be opened and saved in
     * the admin form without tripping its `exists` rules.
     *
     * The set covers every status the list derives in `promotionFromApi`
     * (see `src/services/promotions.js` in the frontend): active, scheduled,
     * paused via `is_active`, expired by `expires_at`, and exhausted by usage
     * cap — plus open-ended and uncapped rows, and one with no banner so the
     * gradient fallback has something to show.
     *
     * `used_count` is never written from here: it is recounted from
     * `coupon_usages`, which the demo order seeders write as they apply each
     * promotion, so the counter always matches the usage rows behind it.
     *
     * Idempotent: re-running updates the row for a code rather than stacking
     * duplicates or tripping the unique index.
     */
    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        foreach ($this->promotions() as $promotion) {
            $coupon = Coupon::withTrashed()->firstOrNew(['code' => $promotion['code']]);
            $coupon->fill([
                'name' => $promotion['name'],
                'description' => $promotion['description'],
                'banner_image' => $promotion['banner_image'],
                'kind' => $promotion['kind'],
                'type' => $promotion['type'],
                'value' => $promotion['value'],
                'min_order_amount' => $promotion['min_order_amount'],
                'max_discount_amount' => $promotion['max_discount_amount'] ?? null,
                'starts_at' => $promotion['starts_at'],
                'expires_at' => $promotion['expires_at'],
                'usage_limit' => $promotion['usage_limit'],
                'user_limit' => $promotion['user_limit'],
                'is_active' => $promotion['is_active'],
                'include_subcategories' => $promotion['target']['include_subcategories'] ?? false,
            ]);
            if (! $coupon->exists) {
                $coupon->used_count = 0;
            }
            $coupon->save();

            if ($coupon->trashed()) {
                $coupon->restore();
            }

            $this->syncTargets($coupon, $promotion['target']);
        }

        self::syncUsageCounts();

        $this->command?->info(sprintf('Seeded %d targeted promotions.', count($this->promotions())));
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column((new self)->promotions(), 'code');
    }

    /**
     * Sets each demo promotion's `used_count` to the usage rows recorded for
     * it. Only this seeder's codes are touched.
     */
    public static function syncUsageCounts(): void
    {
        $counts = CouponUsage::query()
            ->selectRaw('coupon_id, count(*) as uses')
            ->groupBy('coupon_id')
            ->pluck('uses', 'coupon_id');

        Coupon::withTrashed()
            ->whereIn('code', self::codes())
            ->get()
            ->each(function (Coupon $coupon) use ($counts): void {
                $uses = (int) ($counts[$coupon->id] ?? 0);
                if ((int) $coupon->used_count !== $uses) {
                    Coupon::withTrashed()->whereKey($coupon->id)->update(['used_count' => $uses]);
                }
            });
    }

    /**
     * Writes the one target type a promotion has and clears the others, so a
     * promotion that changed target type between runs is never left with two.
     *
     * @param  array<string, mixed>|null  $target
     */
    private function syncTargets(Coupon $coupon, ?array $target): void
    {
        $products = [];
        $variants = [];
        $categories = [];
        $groups = [];
        $type = $target['type'] ?? null;

        if ($type === Coupon::TARGET_PRODUCTS) {
            $products = $this->productIds($coupon->code, $target['categories'], $target['limit'] ?? self::MAX_PRODUCTS);
            $variants = isset($target['variants']) ? $this->variantIds($target['variants']) : [];
        } elseif ($type === Coupon::TARGET_CATEGORIES) {
            $categories = Category::query()->whereIn('slug', $target['categories'])->pluck('id')->all();
            $this->assertFound($coupon->code, 'categories', $target['categories'], $categories);
        } elseif ($type === Coupon::TARGET_CUSTOMER_GROUPS) {
            $groups = CustomerGroup::query()->whereIn('slug', $target['groups'])->pluck('id')->all();
            $this->assertFound($coupon->code, 'customer groups', $target['groups'], $groups);
        }

        Coupon::withTrashed()->whereKey($coupon->id)->update(['target_type' => $type]);
        $coupon->products()->sync($products);
        $coupon->variants()->sync($variants);
        $coupon->categories()->sync($categories);
        $coupon->customerGroups()->sync($groups);
    }

    /**
     * Active products in the given categories, in a stable order. Fails the
     * seed rather than writing a product promotion outside the 10-20 range.
     *
     * @param  list<string>  $categorySlugs
     * @return list<string>
     */
    private function productIds(string $code, array $categorySlugs, int $limit): array
    {
        $ids = Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->whereIn('slug', $categorySlugs))
            ->orderBy('sku')
            ->limit(min($limit, self::MAX_PRODUCTS))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (count($ids) < self::MIN_PRODUCTS) {
            throw new RuntimeException(sprintf(
                'Promotion %s needs at least %d active products in [%s] but found %d. Run ProductCatalogSeeder, StockAlertSeeder and DemoCatalogSeeder first.',
                $code,
                self::MIN_PRODUCTS,
                implode(', ', $categorySlugs),
                count($ids),
            ));
        }

        return $ids;
    }

    /**
     * Active variants of active products in a category whose attribute
     * matches, e.g. every 32GB laptop configuration.
     *
     * @param  array{category: string, attribute: string, value: string}  $filter
     * @return list<int>
     */
    private function variantIds(array $filter): array
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->where('attributes->'.$filter['attribute'], $filter['value'])
            ->whereHas('product', fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('category', fn ($category) => $category->where('slug', $filter['category'])))
            ->orderBy('sku')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<string>  $wanted
     * @param  list<int>  $found
     */
    private function assertFound(string $code, string $what, array $wanted, array $found): void
    {
        if (count($found) !== count($wanted)) {
            throw new RuntimeException(sprintf(
                'Promotion %s targets %s [%s] but only %d exist. Run CategorySeeder and CustomerGroupSeeder first.',
                $code,
                $what,
                implode(', ', $wanted),
                count($found),
            ));
        }
    }

    /**
     * Real computer-store themed banner images so each promotion visually matches
     * the storefront catalog and product categories.
     */
    private function banner(string $seed): string
    {
        $images = [
            'bekie-rtx4070' => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?auto=format&fit=crop&w=1600&q=80',
            'bekie-laptop-save25' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1600&q=80',
            'bekie-ship-monitor' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=1600&q=80',
            'bekie-gamer-pc' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=1600&q=80',
            'bekie-ssd-clearance' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?auto=format&fit=crop&w=1600&q=80',
            'bekie-ram-flash' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1600&q=80',
            'bekie-vip-workstation' => 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=1600&q=80',
            'bekie-weekend-monitor' => 'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=1600&q=80',
            'bekie-desk-set' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1600&q=80',
            'bekie-gaming-setup' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=1600&q=80',
            'bekie-student-lap' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1600&q=80',
            'bekie-ship-pc' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=1600&q=80',
            'bekie-starter-pc' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=1600&q=80',
            'bekie-trade-pc' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=1600&q=80',
            'bekie-memory-storage' => 'https://images.unsplash.com/photo-1555618568-8016a3d4c07c?auto=format&fit=crop&w=1600&q=80',
            'bekie-accessory-gift' => 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?auto=format&fit=crop&w=1600&q=80',
            'bekie-b2g1-accessory' => 'https://images.unsplash.com/photo-1589254065909-b7086229d08c?auto=format&fit=crop&w=1600&q=80',
            'bekie-audio-peripherals' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=1600&q=80',
            'bekie-weekend-pc' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=1600&q=80',
        ];

        return $images[$seed] ?? 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1600&q=80';
    }

    /** @return array{type: string, categories: list<string>, limit?: int, variants?: array<string, string>} */
    private static function products(array $categories, int $limit = self::MAX_PRODUCTS): array
    {
        return ['type' => Coupon::TARGET_PRODUCTS, 'categories' => $categories, 'limit' => $limit];
    }

    /** @return array{type: string, categories: list<string>, include_subcategories: bool} */
    private static function categories(array $slugs, bool $includeSubcategories = false): array
    {
        return ['type' => Coupon::TARGET_CATEGORIES, 'categories' => $slugs, 'include_subcategories' => $includeSubcategories];
    }

    /** @return array{type: string, groups: list<string>} */
    private static function groups(array $slugs): array
    {
        return ['type' => Coupon::TARGET_CUSTOMER_GROUPS, 'groups' => $slugs];
    }

    /**
     * The 20 original campaign codes, unchanged apart from their targets and
     * the two usage caps the scenario orders now fill, followed by the codes
     * added for cases the originals did not cover.
     *
     * @return array<int, array<string, mixed>>
     */
    private function promotions(): array
    {
        return [
            [
                'name' => 'RTX 4070 Launch Deal',
                'code' => 'RTX4070LAUNCH',
                'kind' => 'flash_sale',
                'description' => '15% off NVIDIA RTX 4070 gaming desktops and GPU builds.',
                'banner_image' => $this->banner('bekie-rtx4070'),
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 800.00,
                'max_discount_amount' => 220.00,
                'starts_at' => now()->subDays(4),
                'expires_at' => now()->addDays(6),
                'usage_limit' => 220,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['graphics-cards', 'desktop-computers']),
            ],
            [
                'name' => 'Laptop Upgrade Bonus',
                'code' => 'LAPTOPSAVE25',
                'kind' => 'bundle',
                'description' => 'Save $25 on gaming laptops, ultrabooks, and workstation upgrades.',
                'banner_image' => $this->banner('bekie-laptop-save25'),
                'type' => 'fixed',
                'value' => 25.00,
                'min_order_amount' => 600.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(18),
                'expires_at' => now()->addDays(32),
                'usage_limit' => 500,
                'user_limit' => 2,
                'is_active' => true,
                'target' => self::categories(['laptops']),
            ],
            [
                'name' => 'Monitor Delivery Saver',
                'code' => 'SHIPMONITOR',
                'kind' => 'free_shipping',
                'description' => 'Free delivery for 4K monitors, gaming displays, and desktop accessories.',
                'banner_image' => $this->banner('bekie-ship-monitor'),
                'type' => 'fixed',
                'value' => 9.99,
                'min_order_amount' => 120.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(30),
                'expires_at' => null,
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                'target' => self::categories(['monitors']),
            ],
            [
                'name' => 'Gaming PC Combo Deal',
                'code' => 'GAMERPC15',
                'kind' => 'bundle',
                'description' => '15% off gaming desktops, GPU bundles, and RGB premium kits.',
                'banner_image' => $this->banner('bekie-gamer-pc'),
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 900.00,
                'max_discount_amount' => 180.00,
                'starts_at' => now()->subDays(12),
                'expires_at' => now()->addDays(18),
                'usage_limit' => 280,
                'user_limit' => 1,
                'is_active' => true,
                // Shares the desktops with RTX4070LAUNCH: same rate, lower cap,
                // so the RTX deal wins on any desktop over $1,200.
                'target' => self::products(['desktop-computers', 'keyboards', 'mice']),
            ],
            [
                'name' => 'Custom PC Build Promo',
                'code' => 'PCBUILD20',
                'kind' => 'bundle',
                'description' => '20% off custom desktop builds with motherboard, RAM, and SSD combos.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 1000.00,
                'max_discount_amount' => 350.00,
                'starts_at' => now()->subDays(8),
                'expires_at' => now()->addDays(210),
                'usage_limit' => 1800,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::categories(['pc-components'], includeSubcategories: true),
            ],
            [
                'name' => 'SSD Summer Clearance',
                'code' => 'SSDSUMMER50',
                'kind' => 'flash_sale',
                'description' => '50% off NVMe SSD and storage upgrades during the clearance run.',
                'banner_image' => $this->banner('bekie-ssd-clearance'),
                'type' => 'percentage',
                'value' => 50.00,
                'min_order_amount' => 150.00,
                'max_discount_amount' => 260.00,
                'starts_at' => now()->subDays(80),
                'expires_at' => now()->subDays(15),
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                'target' => self::categories(['storage'], includeSubcategories: true),
            ],
            [
                'name' => '24-Hour RAM Flash Sale',
                'code' => 'RAMFLASH24',
                'kind' => 'flash_sale',
                'description' => '20% off DDR5 RAM kits and memory upgrades for a limited 24 hours.',
                'banner_image' => $this->banner('bekie-ram-flash'),
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 160.00,
                'max_discount_amount' => 150.00,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->subDays(3),
                // Filled by PromotionUsageOrderSeeder: expired and exhausted.
                'usage_limit' => 3,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::categories(['memory-ram']),
            ],
            [
                'name' => 'VIP Workstation Upgrade',
                'code' => 'VIPWORK100',
                'kind' => 'free_gift',
                'description' => 'VIP members receive $100 off premium workstation and creator PC orders.',
                'banner_image' => $this->banner('bekie-vip-workstation'),
                'type' => 'fixed',
                'value' => 100.00,
                'min_order_amount' => 1500.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(90),
                'expires_at' => null,
                // Filled by PromotionUsageOrderSeeder: running but exhausted.
                'usage_limit' => 3,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::groups([CustomerGroup::VIP_SLUG]),
            ],
            [
                'name' => 'Weekend Monitor Drop',
                'code' => 'WEEKENDMON15',
                'kind' => 'flash_sale',
                'description' => '15% off gaming monitors, office displays, and ultrawide setups.',
                'banner_image' => $this->banner('bekie-weekend-monitor'),
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 250.00,
                'max_discount_amount' => 200.00,
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addDays(4),
                'usage_limit' => 320,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['monitors', 'webcams']),
            ],
            [
                'name' => 'Office Desk Setup Bundle',
                'code' => 'DESKSET40',
                'kind' => 'bundle',
                'description' => '40% off monitor, keyboard, mouse, and desk accessory bundles.',
                'banner_image' => $this->banner('bekie-desk-set'),
                'type' => 'percentage',
                'value' => 40.00,
                'min_order_amount' => 500.00,
                'max_discount_amount' => 350.00,
                'starts_at' => now()->subDays(18),
                'expires_at' => now()->addDays(28),
                'usage_limit' => 210,
                'user_limit' => 2,
                'is_active' => true,
                // Overlaps WEEKENDMON15 on monitors: 40% (min $500) beats 15%.
                'target' => self::products(['monitors', 'keyboards', 'mice']),
            ],
            [
                'name' => 'Gaming Setup Bundle Bonus',
                'code' => 'GAMESYSTEM150',
                'kind' => 'bogo',
                'description' => 'Get $150 off complete gaming setups with a gaming PC and accessories.',
                'banner_image' => $this->banner('bekie-gaming-setup'),
                'type' => 'fixed',
                'value' => 150.00,
                'min_order_amount' => 1600.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(15),
                'expires_at' => now()->addDays(52),
                'usage_limit' => 180,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['laptops', 'desktop-computers']),
            ],
            [
                'name' => 'Student Laptop Savings',
                'code' => 'STUDENTLAP18',
                'kind' => 'bundle',
                'description' => '18% off student laptops, accessories, and productivity bundles.',
                'banner_image' => $this->banner('bekie-student-lap'),
                'type' => 'percentage',
                'value' => 18.00,
                'min_order_amount' => 450.00,
                'max_discount_amount' => 200.00,
                'starts_at' => now()->subDays(35),
                'expires_at' => now()->addDays(65),
                'usage_limit' => 520,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::groups(['students']),
            ],
            [
                'name' => 'Desktop Delivery Deal',
                'code' => 'SHIPPC150',
                'kind' => 'free_shipping',
                'description' => 'Enjoy free shipping on desktop PCs, towers, and workstation upgrades.',
                'banner_image' => $this->banner('bekie-ship-pc'),
                'type' => 'fixed',
                'value' => 12.00,
                'min_order_amount' => 150.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(90),
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                'target' => self::categories(['desktop-computers']),
            ],
            [
                'name' => 'Starter PC Kit Offer',
                'code' => 'STARTERPC30',
                'kind' => 'bundle',
                'description' => '$30 off starter PC kits with monitor, keyboard, and mouse bundles.',
                'banner_image' => $this->banner('bekie-starter-pc'),
                'type' => 'fixed',
                'value' => 30.00,
                'min_order_amount' => 300.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(7),
                'expires_at' => now()->addDays(12),
                'usage_limit' => 380,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['keyboards', 'mice', 'webcams', 'speakers']),
            ],
            [
                'name' => 'Trade-In Desktop Bonus',
                'code' => 'TRADEPC75',
                'kind' => 'free_gift',
                'description' => 'Upgrade your old desktop or laptop with $75 off eligible replacements.',
                'banner_image' => $this->banner('bekie-trade-pc'),
                'type' => 'fixed',
                'value' => 75.00,
                'min_order_amount' => 900.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(25),
                'expires_at' => now()->addDays(72),
                'usage_limit' => 110,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::categories(['laptops', 'desktop-computers']),
            ],
            [
                'name' => 'Memory & Storage Upgrade',
                'code' => 'RAMSTORAGE12',
                'kind' => 'bundle',
                'description' => '12% off RAM and SSD upgrades for gaming desktops and workstations.',
                'banner_image' => $this->banner('bekie-memory-storage'),
                'type' => 'percentage',
                'value' => 12.00,
                'min_order_amount' => 200.00,
                'max_discount_amount' => 140.00,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(26),
                'usage_limit' => 500,
                'user_limit' => 2,
                'is_active' => true,
                'target' => self::categories(['memory-ram', 'storage'], includeSubcategories: true),
            ],
            [
                'name' => 'Accessory Gift with Purchase',
                'code' => 'GIFTACCESSORY',
                'kind' => 'free_gift',
                'description' => 'Receive a bonus accessory pack with premium keyboard, mouse, or headset orders.',
                'banner_image' => $this->banner('bekie-accessory-gift'),
                // A $0 promotion: listed on the products but never the best
                // discount, so it never reaches an order total.
                'type' => 'fixed',
                'value' => 0.00,
                'min_order_amount' => 700.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(45),
                'expires_at' => now()->addDays(85),
                'usage_limit' => 260,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['keyboards', 'mice', 'headphones-headsets']),
            ],
            [
                'name' => 'Buy 2 Get 1 Accessory',
                'code' => 'B2G1ACCESS',
                'kind' => 'bogo',
                'description' => 'Buy two PC accessories and get a third one at a special price.',
                'banner_image' => $this->banner('bekie-b2g1-accessory'),
                'type' => 'fixed',
                'value' => 29.99,
                'min_order_amount' => 220.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(12),
                'expires_at' => now()->addDays(55),
                'usage_limit' => 240,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['cables-adapters', 'power-banks-chargers', 'external-storage', 'webcams', 'networking']),
            ],
            [
                'name' => 'Audio & Peripherals Bundle',
                'code' => 'PERIPH25',
                'kind' => 'bundle',
                'description' => '25% off audio gear, keyboards, headsets, and premium desk accessories.',
                'banner_image' => $this->banner('bekie-audio-peripherals'),
                'type' => 'percentage',
                'value' => 25.00,
                'min_order_amount' => 180.00,
                'max_discount_amount' => 220.00,
                'starts_at' => now()->subDays(6),
                'expires_at' => now()->addDays(22),
                'usage_limit' => 160,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['headphones-headsets', 'speakers', 'keyboards']),
            ],
            [
                'name' => 'Weekend Desktop Delivery',
                'code' => 'WEEKENDPC',
                'kind' => 'free_shipping',
                'description' => 'Free delivery for desktop computers and all-in-one PC orders placed this weekend.',
                'banner_image' => $this->banner('bekie-weekend-pc'),
                'type' => 'fixed',
                'value' => 15.00,
                'min_order_amount' => 0.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(3),
                'expires_at' => now()->addDays(5),
                'usage_limit' => 600,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::groups(['gamers-club']),
            ],

            /*
            | Added for cases the original 20 did not cover.
            */
            [
                'name' => 'GPU & CPU Drop (Coming Soon)',
                'code' => 'GPUDROP12',
                'kind' => 'flash_sale',
                'description' => '12% off graphics cards and processors when the drop opens.',
                'banner_image' => $this->banner('bekie-rtx4070'),
                'type' => 'percentage',
                'value' => 12.00,
                'min_order_amount' => null,
                'max_discount_amount' => 150.00,
                // Scheduled: active, but its window has not opened yet.
                'starts_at' => now()->addDays(3),
                'expires_at' => now()->addDays(10),
                'usage_limit' => 300,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['graphics-cards', 'processors']),
            ],
            [
                'name' => 'CPU + Motherboard Bundle',
                'code' => 'CPUBUNDLE10',
                'kind' => 'bundle',
                'description' => '$10 off each processor and motherboard. Paused while stock is recounted.',
                'banner_image' => $this->banner('bekie-memory-storage'),
                'type' => 'fixed',
                'value' => 10.00,
                'min_order_amount' => null,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(20),
                'expires_at' => now()->addDays(40),
                'usage_limit' => null,
                'user_limit' => null,
                // Paused: inside its window but switched off.
                'is_active' => false,
                'target' => self::products(['processors', 'motherboards']),
            ],
            [
                'name' => 'Upgrade to 32GB',
                'code' => 'UPGRADE32GB',
                'kind' => null,
                'description' => '8% off RAM and SSD upgrades, plus every 32GB laptop configuration.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 8.00,
                'min_order_amount' => null,
                'max_discount_amount' => 120.00,
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(50),
                'usage_limit' => null,
                'user_limit' => 2,
                'is_active' => true,
                // The variant list reaches laptops that are not in the product
                // list, so only their 32GB configurations are discounted.
                'target' => self::products(['memory-ram', 'internal-ssds']) + [
                    'variants' => ['category' => 'laptops', 'attribute' => 'ram', 'value' => '32GB'],
                ],
            ],
            [
                'name' => 'Storage Deal (Limited)',
                'code' => 'STORAGEDEAL5',
                'kind' => 'flash_sale',
                'description' => '$5 off SSDs, hard drives, and portable storage — first two orders only.',
                'banner_image' => $this->banner('bekie-ssd-clearance'),
                'type' => 'fixed',
                'value' => 5.00,
                'min_order_amount' => null,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(14),
                'expires_at' => now()->addDays(14),
                // Filled by PromotionUsageOrderSeeder: a product promotion
                // that is running but exhausted.
                'usage_limit' => 2,
                'user_limit' => 1,
                'is_active' => true,
                'target' => self::products(['internal-ssds', 'hard-drives', 'external-storage']),
            ],
            [
                'name' => 'Components Direct Discount',
                'code' => 'COMPONENTS7',
                'kind' => null,
                'description' => '7% off items filed directly under PC Components (not its subcategories).',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 7.00,
                'min_order_amount' => null,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->addDays(30),
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                // Every component sits in a subcategory, so with subcategories
                // excluded this matches nothing — an edge case on purpose.
                'target' => self::categories(['pc-components']),
            ],
            [
                'name' => 'Dormant Group Offer',
                'code' => 'DORMANTGROUP5',
                'kind' => null,
                'description' => '5% off for a customer group that has been switched off.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 5.00,
                'min_order_amount' => null,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(30),
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                // The group is inactive, so its members never receive this.
                'target' => self::groups(['inactive-promo-group']),
            ],
            [
                'name' => 'B2B Bulk Pricing',
                'code' => 'B2BBULK12',
                'kind' => 'bundle',
                'description' => '12% off for business and wholesale accounts, capped at $300 per item.',
                'banner_image' => $this->banner('bekie-desk-set'),
                'type' => 'percentage',
                'value' => 12.00,
                'min_order_amount' => 1000.00,
                'max_discount_amount' => 300.00,
                'starts_at' => now()->subDays(60),
                'expires_at' => null,
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                'target' => self::groups(['b2b-wholesale']),
            ],
            [
                'name' => 'Welcome Code',
                'code' => 'WELCOME10',
                'kind' => null,
                'description' => '10% off your order, up to $50, when you enter WELCOME10 at checkout.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 50.00,
                'max_discount_amount' => 50.00,
                'starts_at' => now()->subDays(120),
                'expires_at' => null,
                'usage_limit' => null,
                'user_limit' => 1,
                'is_active' => true,
                // No target: applies to the whole order, by code only.
                'target' => null,
            ],
            [
                'name' => 'Big Spender Code',
                'code' => 'BIGSPEND50',
                'kind' => null,
                'description' => '$50 off orders of $1,500 or more with code BIGSPEND50.',
                'banner_image' => null,
                'type' => 'fixed',
                'value' => 50.00,
                'min_order_amount' => 1500.00,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(60),
                'usage_limit' => 500,
                'user_limit' => 3,
                'is_active' => true,
                'target' => null,
            ],
            [
                'name' => 'Last Season Code',
                'code' => 'OLDCODE20',
                'kind' => null,
                'description' => 'An expired code, for the "this code has expired" message.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => null,
                'max_discount_amount' => 100.00,
                'starts_at' => now()->subDays(200),
                'expires_at' => now()->subDays(100),
                'usage_limit' => null,
                'user_limit' => null,
                'is_active' => true,
                'target' => null,
            ],
        ];
    }
}
