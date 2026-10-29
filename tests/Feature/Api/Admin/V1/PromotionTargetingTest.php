<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\ProductPromotionService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(RefreshDatabase::class);

function targetingAdmin(): TestCase
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));
    $token = (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

/** @param array<string, mixed> $overrides */
function targetingPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Targeted',
        'code' => 'TARGET-'.fake()->unique()->numerify('####'),
        'type' => 'percentage',
        'value' => 10,
        'starts_at' => now()->subDay()->toIso8601String(),
        'is_active' => true,
    ], $overrides);
}

function targetGroup(string $slug = 'members', bool $active = true): CustomerGroup
{
    return CustomerGroup::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => $active]);
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('a product promotion is created with only product targets', function (): void {
    $product = Product::factory()->create(['is_active' => true, 'price' => 200, 'thumbnail' => 'https://cdn.test/p.png']);

    $id = targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'products',
        'product_ids' => [$product->id, $product->id],
        'category_ids' => [],
        'customer_group_ids' => [],
    ]))->assertCreated()
        ->assertJsonPath('data.target_type', 'products')
        ->assertJsonPath('data.products.0.id', $product->id)
        ->assertJsonPath('data.products.0.thumbnail', 'https://cdn.test/p.png')
        ->assertJsonPath('data.products.0.price', 200)
        ->json('data.id');

    $promotion = Coupon::findOrFail($id);
    expect($promotion->products()->pluck('products.id')->all())->toBe([$product->id])
        ->and($promotion->categories()->count())->toBe(0)
        ->and($promotion->customerGroups()->count())->toBe(0);
});

test('a category promotion keeps its subcategory flag', function (): void {
    $category = Category::factory()->create(['is_active' => true]);

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'categories',
        'category_ids' => [$category->id],
        'include_subcategories' => true,
    ]))->assertCreated()
        ->assertJsonPath('data.target_type', 'categories')
        ->assertJsonPath('data.include_subcategories', true)
        ->assertJsonPath('data.categories.0.id', $category->id);
});

test('a customer group promotion is created with only group targets', function (): void {
    $group = targetGroup();

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'customer_groups',
        'customer_group_ids' => [$group->id],
        'include_subcategories' => true,
    ]))->assertCreated()
        ->assertJsonPath('data.target_type', 'customer_groups')
        ->assertJsonPath('data.customer_groups.0.id', $group->id)
        // Only meaningful for a category target.
        ->assertJsonPath('data.include_subcategories', false);
});

test('a promotion without a target type is rejected', function (): void {
    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_type');

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload(['target_type' => 'brands']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_type');
});

test('a target type without any selected target is rejected', function (string $type, string $field): void {
    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload(['target_type' => $type, $field => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'products' => ['products', 'product_ids'],
    'categories' => ['categories', 'category_ids'],
    'customer groups' => ['customer_groups', 'customer_group_ids'],
]);

test('a product promotion may target only specific variants', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'name' => '32GB', 'slug' => '32gb', 'sku' => 'RAM-32',
        'price' => 120, 'in_stock' => true, 'stock_quantity' => 4, 'is_active' => true,
    ]);

    $id = targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'products',
        'product_ids' => [],
        'variant_ids' => [$variant->id],
    ]))->assertCreated()
        ->assertJsonPath('data.target_type', 'products')
        ->json('data.id');

    $promotion = Coupon::findOrFail($id);
    expect($promotion->products()->count())->toBe(0)
        ->and($promotion->variants()->pluck('product_variants.id')->all())->toBe([$variant->id]);

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'products', 'product_ids' => [], 'variant_ids' => [],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['product_ids' => 'Select at least one product or variant.']);
});

test('the product picker can ask for active variant counts', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    foreach ([['A', true], ['B', true], ['C', false]] as [$name, $active]) {
        ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'slug' => strtolower($name), 'sku' => "SKU-{$name}",
            'price' => 10, 'in_stock' => true, 'stock_quantity' => 1, 'is_active' => $active,
        ]);
    }

    targetingAdmin()->getJson('/api/v1/admin/products?with_variants_count=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.variants', 2);

    targetingAdmin()->getJson('/api/v1/admin/products')
        ->assertOk()
        ->assertJsonMissingPath('data.items.0.variants');
});

test('mixed target types are rejected', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    $category = Category::factory()->create(['is_active' => true]);
    $group = targetGroup();

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'products',
        'product_ids' => [$product->id],
        'category_ids' => [$category->id],
        'customer_group_ids' => [$group->id],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['category_ids', 'customer_group_ids'])
        ->assertJsonMissingValidationErrors('product_ids');

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'categories',
        'category_ids' => [$category->id],
        'variant_ids' => [1],
    ]))->assertUnprocessable()->assertJsonValidationErrors('variant_ids');

    expect(Coupon::count())->toBe(0);
});

test('unknown target ids are rejected', function (): void {
    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'products',
        'product_ids' => ['not-a-product'],
    ]))->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'categories',
        'category_ids' => [999999],
    ]))->assertUnprocessable()->assertJsonValidationErrors('category_ids.0');

    targetingAdmin()->postJson('/api/v1/admin/promotions', targetingPayload([
        'target_type' => 'customer_groups',
        'customer_group_ids' => [999999],
    ]))->assertUnprocessable()->assertJsonValidationErrors('customer_group_ids.0');
});

test('switching the target type detaches the previous targets', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    $category = Category::factory()->create(['is_active' => true]);
    $promotion = Coupon::create(targetingPayload(['target_type' => 'products']));
    $promotion->products()->sync([$product->id]);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$promotion->id}", [
        'target_type' => 'categories',
        'category_ids' => [$category->id],
        'product_ids' => [],
    ])->assertOk()
        ->assertJsonPath('data.target_type', 'categories')
        ->assertJsonPath('data.product_ids', [])
        ->assertJsonPath('data.category_ids', [$category->id]);

    expect($promotion->products()->count())->toBe(0);
});

test('switching the target type without new targets is rejected and changes nothing', function (): void {
    $product = Product::factory()->create(['is_active' => true]);
    $promotion = Coupon::create(targetingPayload(['target_type' => 'products']));
    $promotion->products()->sync([$product->id]);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$promotion->id}", ['target_type' => 'customer_groups'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_group_ids');

    expect($promotion->fresh()->target_type)->toBe('products')
        ->and($promotion->products()->count())->toBe(1);
});

test('ids for the saved target type can be updated without resending the type', function (): void {
    $first = Product::factory()->create(['is_active' => true]);
    $second = Product::factory()->create(['is_active' => true]);
    $promotion = Coupon::create(targetingPayload(['target_type' => 'products']));
    $promotion->products()->sync([$first->id]);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$promotion->id}", ['product_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.product_ids', [$second->id]);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$promotion->id}", ['category_ids' => [1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_ids');
});

test('an update that leaves targeting out keeps the saved targets', function (): void {
    $group = targetGroup();
    $promotion = Coupon::create(targetingPayload(['target_type' => 'customer_groups']));
    $promotion->customerGroups()->sync([$group->id]);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$promotion->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.customer_group_ids', [$group->id]);
});

test('a legacy untargeted coupon can still be edited without choosing targets', function (): void {
    $legacy = Coupon::create(targetingPayload());

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$legacy->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.target_type', null);

    targetingAdmin()->patchJson("/api/v1/admin/promotions/{$legacy->id}", ['product_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_type');
});

test('show restores the saved target type and its selections', function (): void {
    $parent = Category::factory()->create(['is_active' => true, 'name' => 'Components']);
    $child = Category::factory()->create(['is_active' => true, 'parent_id' => $parent->id, 'name' => 'GPUs']);
    $promotion = Coupon::create(targetingPayload(['target_type' => 'categories', 'include_subcategories' => true]));
    $promotion->categories()->sync([$child->id]);

    targetingAdmin()->getJson("/api/v1/admin/promotions/{$promotion->id}")
        ->assertOk()
        ->assertJsonPath('data.target_type', 'categories')
        ->assertJsonPath('data.include_subcategories', true)
        ->assertJsonPath('data.categories.0.name', 'GPUs')
        ->assertJsonPath('data.categories.0.parent.name', 'Components');
});

test('checkout prices each target type with the backend rules', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_active' => true, 'category_id' => $category->id, 'price' => 100, 'sale_price' => null]);
    $other = Product::factory()->create(['is_active' => true, 'price' => 100, 'sale_price' => null]);
    $group = targetGroup('insiders');
    $member = User::factory()->create();
    $member->customerGroups()->attach($group->id);
    $outsider = User::factory()->create();

    $categoryPromo = Coupon::create(targetingPayload([
        'target_type' => 'categories', 'type' => 'fixed', 'value' => 30, 'max_discount_amount' => 25,
    ]));
    $categoryPromo->categories()->sync([$category->id]);
    $groupPromo = Coupon::create(targetingPayload(['target_type' => 'customer_groups', 'value' => 10]));
    $groupPromo->customerGroups()->sync([$group->id]);

    $lines = collect([
        'in_category' => ['product' => $product, 'quantity' => 1, 'unit_price' => 100.0],
        'elsewhere' => ['product' => $other, 'quantity' => 1, 'unit_price' => 100.0],
    ]);
    $service = app(ProductPromotionService::class);

    $forOutsider = $service->priceLines($lines, $outsider->id);
    expect($forOutsider['in_category']['discount'])->toBe(25.0)
        ->and($forOutsider['in_category']['promotion']->id)->toBe($categoryPromo->id)
        ->and($forOutsider['elsewhere']['discount'])->toBe(0.0);

    $forMember = $service->priceLines($lines, $member->id);
    expect($forMember['in_category']['discount'])->toBe(25.0)
        ->and($forMember['elsewhere']['discount'])->toBe(10.0)
        ->and($forMember['elsewhere']['promotion']->id)->toBe($groupPromo->id);
});

test('the migration backfills the target type of existing promotions', function (): void {
    $migration = require database_path('migrations/2026_10_29_142129_add_target_type_to_coupons_table.php');
    $migration->down();

    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $group = targetGroup();
    $insert = fn (string $code): int => DB::table('coupons')->insertGetId([
        'name' => $code, 'code' => $code, 'type' => 'percentage', 'value' => 5, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $productPromo = $insert('LEGACY-P');
    $categoryPromo = $insert('LEGACY-C');
    $groupPromo = $insert('LEGACY-G');
    $mixedPromo = $insert('LEGACY-M');
    $untargeted = $insert('LEGACY-N');
    DB::table('coupon_product')->insert([
        ['coupon_id' => $productPromo, 'product_id' => $product->id],
        ['coupon_id' => $mixedPromo, 'product_id' => $product->id],
    ]);
    DB::table('coupon_category')->insert(['coupon_id' => $categoryPromo, 'category_id' => $category->id]);
    DB::table('coupon_customer_group')->insert([
        ['coupon_id' => $groupPromo, 'customer_group_id' => $group->id],
        ['coupon_id' => $mixedPromo, 'customer_group_id' => $group->id],
    ]);

    $migration->up();

    expect(DB::table('coupons')->orderBy('id')->pluck('target_type', 'id')->all())->toBe([
        $productPromo => 'products',
        $categoryPromo => 'categories',
        $groupPromo => 'customer_groups',
        $mixedPromo => 'products',
        $untargeted => null,
    ])
        // Nothing is detached: legacy checkout pricing is unchanged.
        ->and(DB::table('coupon_customer_group')->where('coupon_id', $mixedPromo)->count())->toBe(1);
});
