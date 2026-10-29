<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(RefreshDatabase::class);

function promoToken(): string
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    return (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];
}

function asPromoAdmin(): TestCase
{
    return test()->withHeader('Authorization', 'Bearer '.promoToken());
}

function promoProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'category_id' => Category::factory()->create()->id,
        'name' => 'Promo Product',
        'slug' => 'promo-product',
        'sku' => 'PROMO-SKU',
        'price' => 10.00,
        'stock_quantity' => 5,
        'is_active' => true,
    ], $attributes));
}

function promoCoupon(string $code, array $attributes = []): Coupon
{
    return Coupon::create(array_merge([
        'name' => 'Promo '.$code,
        'code' => $code,
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'target_type' => Coupon::TARGET_PRODUCTS,
    ], $attributes));
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('store attaches the submitted promotions', function (): void {
    $category = Category::factory()->create();
    $a = promoCoupon('PROMO-A');
    $b = promoCoupon('PROMO-B');

    $response = asPromoAdmin()->postJson('/api/v1/admin/products', [
        'category_id' => $category->id,
        'name' => 'New Product',
        'sku' => 'NEW-SKU',
        'price' => 25.00,
        'promotion_ids' => [$a->id, $b->id],
    ])->assertCreated();

    expect($response->json('data.promotion_ids'))->toEqualCanonicalizing([$a->id, $b->id]);

    $product = Product::where('sku', 'NEW-SKU')->firstOrFail();
    expect($product->promotions()->pluck('coupons.id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

test('update replaces the applied promotions', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A');
    $b = promoCoupon('PROMO-B');
    $c = promoCoupon('PROMO-C');
    $product->promotions()->sync([$a->id, $b->id]);

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [$b->id, $c->id],
    ])->assertOk();

    expect($product->promotions()->pluck('coupons.id')->all())
        ->toEqualCanonicalizing([$b->id, $c->id]);
});

test('an empty promotion_ids array detaches every promotion', function (): void {
    $product = promoProduct();
    $product->promotions()->sync([promoCoupon('PROMO-A')->id]);

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [],
    ])->assertOk();

    expect($product->promotions()->count())->toBe(0);
});

test('omitting promotion_ids leaves the applied promotions alone', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A');
    $product->promotions()->sync([$a->id]);

    // A save from a form that never touched promotions must not clear them.
    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'name' => 'Renamed Product',
    ])->assertOk();

    expect($product->promotions()->pluck('coupons.id')->all())->toBe([$a->id]);
});

test('show returns the applied promotions with their labels', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A', ['name' => 'Black Friday Sale']);
    $product->promotions()->sync([$a->id]);

    $response = asPromoAdmin()
        ->getJson('/api/v1/admin/products/'.$product->id)
        ->assertOk();

    expect($response->json('data.promotion_ids'))->toBe([$a->id]);
    expect($response->json('data.promotions.0.name'))->toBe('Black Friday Sale');
});

test('a duplicate id in the payload attaches the promotion once', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A');

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [$a->id, $a->id],
    ])->assertOk();

    expect($product->promotions()->pluck('coupons.id')->all())->toBe([$a->id]);
});

test('an unknown promotion id is rejected', function (): void {
    $product = promoProduct();

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [999999],
    ])->assertStatus(422)->assertJsonValidationErrors('promotion_ids.0');
});

test('a soft-deleted promotion is rejected and stops being returned', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A');
    $product->promotions()->sync([$a->id]);
    $a->delete();

    // The pivot row survives the soft delete, but the relation filters it out.
    expect($product->promotions()->count())->toBe(0);

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [$a->id],
    ])->assertStatus(422)->assertJsonValidationErrors('promotion_ids.0');
});

test('promotion targeting rejects inactive target records', function (): void {
    $inactiveCategory = Category::factory()->create(['is_active' => false]);
    $inactiveGroup = CustomerGroup::create(['name' => 'Disabled', 'slug' => 'disabled', 'is_active' => false]);
    $payload = [
        'name' => 'Invalid targeting',
        'code' => 'INVALID-TARGETS',
        'type' => 'percentage',
        'value' => 10,
        'starts_at' => now()->toIso8601String(),
    ];

    asPromoAdmin()->postJson('/api/v1/admin/promotions', $payload + [
        'target_type' => 'categories',
        'category_ids' => [$inactiveCategory->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('category_ids.0');

    asPromoAdmin()->postJson('/api/v1/admin/promotions', $payload + [
        'target_type' => 'customer_groups',
        'customer_group_ids' => [$inactiveGroup->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_group_ids.0');
});

test('the product form only attaches product-targeted promotions', function (): void {
    $product = promoProduct();
    $categoryPromotion = promoCoupon('CAT-ONLY', ['target_type' => Coupon::TARGET_CATEGORIES]);

    asPromoAdmin()->putJson('/api/v1/admin/products/'.$product->id, [
        'promotion_ids' => [$categoryPromotion->id],
    ])->assertStatus(422)->assertJsonValidationErrors('promotion_ids.0');
});

test('deleting a product removes its pivot rows', function (): void {
    $product = promoProduct();
    $a = promoCoupon('PROMO-A');
    $product->promotions()->sync([$a->id]);

    // Product uses SoftDeletes, so the cascade only fires on a force delete.
    $product->forceDelete();

    expect(DB::table('coupon_product')->where('product_id', $product->id)->count())->toBe(0);
});
