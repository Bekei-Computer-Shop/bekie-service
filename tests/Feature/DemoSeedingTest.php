<?php

declare(strict_types=1);

use App\Http\Requests\Api\Admin\V1\StoreOrderRequest;
use App\Http\Requests\Api\Admin\V1\UpdateOrderRequest;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\PromotionSeeder;
use Database\Seeders\Support\DemoData;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['seeding.admin_password' => 'seeded-secret-123']);
});

/**
 * DatabaseSeeder's full list, with model events muted exactly as
 * DatabaseSeeder mutes them — minus NotificationSeeder, whose cleanup query
 * is Postgres-only (`data::jsonb`) and cannot run on the SQLite test database.
 */
function seedDemoDatabase(): void
{
    $seeder = new class extends Seeder
    {
        use WithoutModelEvents;

        public function run(): void
        {
            $this->call(array_values(array_diff(DatabaseSeeder::SEEDERS, [NotificationSeeder::class])));
            PromotionSeeder::syncUsageCounts();
        }
    };

    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder->setContainer(app())->setCommand($command)->__invoke();
}

/** Row counts that must not move when the full seed runs again. */
function demoSeedCounts(): array
{
    return [
        'products' => Product::withTrashed()->count(),
        'variants' => ProductVariant::withTrashed()->count(),
        'serials' => ProductSerial::count(),
        'orders' => Order::withTrashed()->count(),
        'coupons' => Coupon::withTrashed()->count(),
        'usages' => CouponUsage::count(),
        'payments' => Payment::withTrashed()->count(),
        'users' => User::withTrashed()->count(),
    ];
}

/** @return array<string, int> code => usage rows */
function usageRowsByCode(): array
{
    $rows = CouponUsage::query()->selectRaw('coupon_code, count(*) as uses')->groupBy('coupon_code')->pluck('uses', 'coupon_code');

    return $rows->map(fn ($uses): int => (int) $uses)->sortKeys()->all();
}

test('admin seeder hashes SEED_ADMIN_PASSWORD for every admin login', function (): void {
    $this->seed(AdminUserSeeder::class);

    foreach (['admin@example.com', 'staff@gmail.com', 'manager@example.com'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        expect(Hash::check('seeded-secret-123', $user->password))->toBeTrue()
            ->and($user->password)->not->toBe('seeded-secret-123');
    }
});

test('admin seeder refuses to run outside local without a password and changes nothing', function (): void {
    config(['seeding.admin_password' => null]);
    app()['env'] = 'production';

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'SEED_ADMIN_PASSWORD must be set');

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse();
});

test('admin seeder falls back to the documented password only in local', function (): void {
    config(['seeding.admin_password' => null]);
    app()['env'] = 'local';

    $this->seed(AdminUserSeeder::class);

    expect(Hash::check('password', User::where('email', 'admin@example.com')->value('password')))->toBeTrue();
});

test('demo seeders refuse to run in production', function (): void {
    app()['env'] = 'production';

    expect(fn () => DemoData::ensureAllowed(PromotionSeeder::class))->toThrow(RuntimeException::class);
    $this->artisan('demo:reset', ['--force' => true])->assertFailed();
});

test('the full seed is relationally consistent and repeatable', function (): void {
    seedDemoDatabase();

    // Catalog: every product has a variant, product stock is the sum of its
    // variants, and the busiest products have 5-10 configurations.
    $products = Product::withTrashed()->withCount('variants')->with('variants')->get();
    expect($products->count())->toBeGreaterThan(100)
        ->and(Product::where('is_active', true)->count())->toBeGreaterThanOrEqual(90);
    foreach ($products as $product) {
        expect($product->variants_count)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(10)
            ->and((int) $product->stock_quantity)->toBe((int) $product->variants->sum('stock_quantity'), $product->sku);
        expect($product->variants->where('is_default', true))->toHaveCount(1);
    }
    expect(Product::has('variants', '>=', 5)->count())->toBeGreaterThanOrEqual(25);

    // Serialized stock: one available serial per unit on hand.
    ProductVariant::where('is_serialized', true)->get()->each(function (ProductVariant $variant): void {
        $available = ProductSerial::where('product_variant_id', $variant->id)->where('status', ProductSerial::AVAILABLE)->count();
        expect($available)->toBe((int) $variant->stock_quantity, $variant->sku);
    });

    // Promotions: one target type each, product promotions name 10-20
    // products, and every counter equals its usage rows.
    foreach (Coupon::withTrashed()->with(['products', 'variants', 'categories', 'customerGroups'])->get() as $coupon) {
        $types = array_keys(array_filter([
            Coupon::TARGET_PRODUCTS => $coupon->products->isNotEmpty() || $coupon->variants->isNotEmpty(),
            Coupon::TARGET_CATEGORIES => $coupon->categories->isNotEmpty(),
            Coupon::TARGET_CUSTOMER_GROUPS => $coupon->customerGroups->isNotEmpty(),
        ]));
        expect($types)->toBe($coupon->target_type === null ? [] : [$coupon->target_type], $coupon->code);

        if ($coupon->target_type === Coupon::TARGET_PRODUCTS) {
            expect($coupon->products->count())->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(20);
            expect($coupon->products->every(fn (Product $product) => $product->is_active))->toBeTrue($coupon->code);
        }

        expect((int) $coupon->used_count)->toBe(CouponUsage::where('coupon_id', $coupon->id)->count(), $coupon->code);
        if ($coupon->usage_limit !== null) {
            expect((int) $coupon->used_count)->toBeLessThanOrEqual((int) $coupon->usage_limit);
        }
    }
    expect(Coupon::count())->toBe(count(PromotionSeeder::codes()))
        ->and(Coupon::whereNotIn('code', PromotionSeeder::codes())->count())->toBe(0);

    // The scenario orders exhaust exactly the promotions meant to be exhausted.
    foreach (['RAMFLASH24' => 3, 'VIPWORK100' => 3, 'STORAGEDEAL5' => 2] as $code => $uses) {
        $coupon = Coupon::where('code', $code)->firstOrFail();
        expect((int) $coupon->used_count)->toBe($uses, $code)->and((int) $coupon->usage_limit)->toBe($uses, $code);
    }

    // ...and the group and code scenarios each land on the promotion they show.
    foreach (['ORD-PU-B2B-1' => 'B2BBULK12', 'ORD-PU-STU-1' => 'STUDENTLAP18', 'ORD-PU-RTX-1' => 'RTX4070LAUNCH', 'ORD-PU-WEL-1' => 'WELCOME10'] as $number => $code) {
        $order = Order::where('order_number', $number)->firstOrFail();
        expect(CouponUsage::where('order_id', $order->id)->where('coupon_code', $code)->exists())->toBeTrue("{$number} should use {$code}");
    }
    $exhaustedVipOrder = Order::where('order_number', 'ORD-PU-VIP-4')->firstOrFail();
    expect(CouponUsage::where('order_id', $exhaustedVipOrder->id)->where('coupon_code', 'VIPWORK100')->exists())->toBeFalse();

    // Orders: valid enums, a uuid, totals that add up, a payment per card order.
    $orders = Order::whereIn('metadata->seeded_by', DemoData::ORDER_TAGS)->with('items')->get();
    expect($orders->count())->toBeGreaterThan(100);
    foreach ($orders as $order) {
        expect($order->uuid)->not->toBeNull()
            ->and($order->status)->toBeIn(UpdateOrderRequest::STATUSES)
            ->and($order->payment_method)->toBeIn(StoreOrderRequest::PAYMENT_METHODS)
            ->and($order->payment_status)->toBeIn(StoreOrderRequest::PAYMENT_STATUSES)
            ->and(round((float) $order->items->sum('subtotal'), 2))->toBe(round((float) $order->subtotal, 2), $order->order_number);

        $codeDiscount = (float) CouponUsage::where('order_id', $order->id)->where('metadata->source', 'coupon_code')->sum('discount_amount');
        expect(round((float) $order->items->sum('discount') + $codeDiscount, 2))->toBe(round((float) $order->discount_total, 2), $order->order_number);

        // Prices include tax (config/taxes.php), so tax is not added on top.
        expect(round((float) $order->subtotal - (float) $order->discount_total + (float) $order->shipping_total, 2))
            ->toBe(round((float) $order->grand_total, 2), $order->order_number);

        if ($order->payment_method === 'aba_payway') {
            expect(Payment::where('order_id', $order->id)->exists())->toBeTrue($order->order_number);
        }
    }

    // Sold serials point at their order and customer.
    ProductSerial::where('status', ProductSerial::SOLD)->with('order')->get()->each(function (ProductSerial $serial): void {
        expect($serial->order)->not->toBeNull()
            ->and((int) $serial->customer_id)->toBe((int) $serial->order->user_id)
            ->and($serial->warranty_end_at)->not->toBeNull();
    });

    // Running everything again adds nothing.
    $before = demoSeedCounts();
    seedDemoDatabase();
    expect(demoSeedCounts())->toBe($before);
});

test('demo:reset rebuilds only the demo orders and leaves real data alone', function (): void {
    seedDemoDatabase();

    // A real order with a real promotion's usage, created outside the seeders.
    $customer = User::factory()->create();
    $realCoupon = Coupon::create(['code' => 'REALSHOP5', 'type' => 'fixed', 'value' => 5, 'used_count' => 1, 'is_active' => true]);
    $realOrder = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'order_number' => 'ORD-REAL-0001',
        'subtotal' => 100,
        'discount_total' => 5,
        'grand_total' => 95,
        'status' => 'completed',
        'payment_method' => 'cod',
        'payment_status' => 'paid',
    ]);
    CouponUsage::create(['coupon_id' => $realCoupon->id, 'user_id' => $customer->id, 'order_id' => $realOrder->id, 'coupon_code' => 'REALSHOP5', 'discount_amount' => 5]);

    $demoOrders = Order::whereIn('metadata->seeded_by', DemoData::ORDER_TAGS)->count();
    $usageBefore = usageRowsByCode();

    $this->artisan('demo:reset', ['--force' => true])->assertSuccessful();

    expect(Order::whereIn('metadata->seeded_by', DemoData::ORDER_TAGS)->count())->toBe($demoOrders)
        ->and(Order::whereKey($realOrder->id)->exists())->toBeTrue()
        ->and(CouponUsage::where('order_id', $realOrder->id)->count())->toBe(1)
        ->and((int) $realCoupon->fresh()->used_count)->toBe(1)
        ->and(usageRowsByCode())->toBe($usageBefore);

    foreach (Coupon::whereIn('code', PromotionSeeder::codes())->get() as $coupon) {
        expect((int) $coupon->used_count)->toBe(CouponUsage::where('coupon_id', $coupon->id)->count(), $coupon->code);
    }

    // Serials and reviews of the deleted orders are gone, not orphaned.
    expect(ProductSerial::where('receiving_reference', DemoData::ORDER_SERIAL_REFERENCE)->whereNull('order_id')->count())->toBe(0);

    // A second reset lands in the same place.
    $this->artisan('demo:reset', ['--force' => true])->assertSuccessful();
    expect(usageRowsByCode())->toBe($usageBefore);
});
