<?php

declare(strict_types=1);

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\ProductDefaultVariantService;
use App\Services\ProductSerialService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(RefreshDatabase::class);

function defaultVariantAdmin(): TestCase
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $token = (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

/** @return array<string, mixed> */
function defaultVariantPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::factory()->create()->id,
        'name' => 'Gaming Laptop',
        'sku' => 'LAPTOP-01',
        'price' => 1200,
        'sale_price' => 1100,
        'cost_price' => 900,
        'stock_quantity' => 7,
        'min_stock_alert' => 2,
        'is_serialized' => true,
    ], $overrides);
}

/** @return array<string, mixed> */
function variantRow(string $sku, array $overrides = []): array
{
    return array_merge(['name' => $sku, 'sku' => $sku, 'price' => 100, 'stock_quantity' => 1], $overrides);
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('creating a product without variants generates exactly one default variant', function (array $variants): void {
    $response = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload($variants))
        ->assertCreated()
        ->assertJsonStructure(['status', 'message', 'data' => ['id', 'variants', 'default_variant_id']]);

    $product = Product::findOrFail($response->json('data.id'));
    $variant = $product->variants()->sole();

    expect($variant->product_id)->toBe($product->id)
        ->and($variant->name)->toBe(ProductDefaultVariantService::DEFAULT_NAME)
        ->and($variant->sku)->toBe('LAPTOP-01-DEFAULT')
        ->and($variant->slug)->toBe('laptop-01-default')
        ->and((bool) $variant->is_default)->toBeTrue()
        ->and((bool) $variant->is_active)->toBeTrue()
        ->and((float) $variant->price)->toBe(1200.0)
        ->and((float) $variant->sale_price)->toBe(1100.0)
        ->and((float) $variant->cost_price)->toBe(900.0)
        ->and((int) $variant->stock_quantity)->toBe(7)
        ->and((int) $variant->min_stock_alert)->toBe(2)
        ->and($variant->is_serialized)->toBeTrue()
        ->and((bool) $variant->in_stock)->toBeTrue()
        ->and($response->json('data.default_variant_id'))->toBe($variant->id)
        ->and($response->json('data.variants.0.sku'))->toBe('LAPTOP-01-DEFAULT')
        ->and($response->json('data.variants.0.is_default'))->toBeTrue();
})->with([
    'variants omitted' => [[]],
    'empty variants array' => [['variants' => []]],
]);

test('the generated SKU skips values already taken, soft-deleted variants included', function (): void {
    $taken = ProductVariant::factory()->create(['sku' => 'LAPTOP-01-DEFAULT', 'slug' => 'taken-slug']);
    $taken->delete();

    $id = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload())
        ->assertCreated()
        ->json('data.id');

    expect(Product::findOrFail($id)->variants()->sole()->sku)->toBe('LAPTOP-01-DEFAULT-2');
});

test('the generated SKU stays within the 64 character variant SKU limit', function (): void {
    $sku = str_repeat('A', 64);

    $id = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload(['sku' => $sku]))
        ->assertCreated()
        ->json('data.id');

    $generated = Product::findOrFail($id)->variants()->sole()->sku;

    expect(strlen($generated))->toBe(64)
        ->and($generated)->toEndWith('-DEFAULT');
});

test('creating a product with variants creates only those variants and keeps the selected default', function (): void {
    $response = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload([
            'variants' => [
                variantRow('LAPTOP-16GB'),
                variantRow('LAPTOP-32GB', ['is_default' => true]),
                variantRow('LAPTOP-64GB', ['is_default' => false]),
            ],
        ]))
        ->assertCreated();

    $product = Product::findOrFail($response->json('data.id'));
    $default = $product->variants()->where('sku', 'LAPTOP-32GB')->sole();

    expect($product->variants()->count())->toBe(3)
        ->and($product->variants()->where('sku', 'like', '%-DEFAULT%')->exists())->toBeFalse()
        ->and($product->variants()->where('is_default', true)->pluck('id')->all())->toBe([$default->id])
        ->and($response->json('data.default_variant_id'))->toBe($default->id);
});

test('without a selected default the first active variant becomes the default', function (): void {
    $id = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload([
            'variants' => [
                variantRow('LAPTOP-OFF', ['is_active' => false]),
                variantRow('LAPTOP-ON'),
                variantRow('LAPTOP-ON-2'),
            ],
        ]))
        ->assertCreated()
        ->json('data.id');

    expect(Product::findOrFail($id)->variants()->where('is_default', true)->pluck('sku')->all())
        ->toBe(['LAPTOP-ON']);
});

test('more than one default variant is rejected without creating anything', function (): void {
    defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload([
            'variants' => [
                variantRow('LAPTOP-A', ['is_default' => true]),
                variantRow('LAPTOP-B', ['is_default' => true]),
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.1.is_default']);

    expect(Product::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});

test('a variant SKU already in use is a validation error on create, not a server error', function (): void {
    ProductVariant::factory()->create(['sku' => 'LAPTOP-TAKEN']);

    defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload([
            'variants' => [variantRow('LAPTOP-TAKEN'), variantRow('LAPTOP-FREE')],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.0.sku']);

    expect(Product::where('sku', 'LAPTOP-01')->exists())->toBeFalse()
        ->and(ProductVariant::where('sku', 'LAPTOP-FREE')->exists())->toBeFalse();
});

test('a variant of another product cannot be selected as the default', function (): void {
    $product = Product::factory()->create();
    $own = ProductVariant::factory()->default()->create(['product_id' => $product->id]);
    $foreign = ProductVariant::factory()->create();

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'replace_variants' => false,
            'variants' => [
                ['id' => $foreign->id, 'name' => $foreign->name, 'sku' => $foreign->sku, 'is_default' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.0.id']);

    expect((bool) $own->fresh()->is_default)->toBeTrue()
        ->and((bool) $foreign->fresh()->is_default)->toBeFalse()
        ->and($foreign->fresh()->product_id)->not->toBe($product->id);
});

test('updating unrelated product fields neither duplicates nor changes the variants', function (): void {
    $id = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload())
        ->assertCreated()
        ->json('data.id');
    $variant = Product::findOrFail($id)->variants()->sole();

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$id, ['name' => 'Renamed Laptop', 'price' => 1300])
        ->assertOk()
        ->assertJsonPath('data.default_variant_id', $variant->id);

    $variants = Product::findOrFail($id)->variants()->get();

    expect($variants)->toHaveCount(1)
        ->and($variants->first()->id)->toBe($variant->id)
        ->and((bool) $variants->first()->is_default)->toBeTrue();
});

test('updating a legacy product without variants does not generate one', function (): void {
    $product = Product::factory()->create();

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, ['name' => 'Legacy Renamed'])
        ->assertOk();

    expect($product->variants()->count())->toBe(0);
});

test('selecting another variant as the default moves the flag', function (): void {
    $product = Product::factory()->create();
    $first = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'sort_order' => 0]);
    $second = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'replace_variants' => false,
            'variants' => [
                ['id' => $first->id, 'name' => $first->name, 'sku' => $first->sku, 'is_default' => false],
                ['id' => $second->id, 'name' => $second->name, 'sku' => $second->sku, 'is_default' => true],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.default_variant_id', $second->id);

    expect((bool) $first->fresh()->is_default)->toBeFalse()
        ->and((bool) $second->fresh()->is_default)->toBeTrue();
});

test('an update that names no default keeps the existing one', function (): void {
    $product = Product::factory()->create();
    $first = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
    $second = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'sort_order' => 1]);

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'replace_variants' => false,
            'variants' => [
                ['id' => $first->id, 'name' => 'Renamed', 'sku' => $first->sku],
            ],
        ])
        ->assertOk();

    expect((bool) $second->fresh()->is_default)->toBeTrue()
        ->and((bool) $first->fresh()->is_default)->toBeFalse();
});

test('removing the default variant hands the default to a remaining variant', function (): void {
    $product = Product::factory()->create();
    $default = ProductVariant::factory()->default()->create(['product_id' => $product->id, 'sort_order' => 0]);
    $other = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'replace_variants' => false,
            'remove_missing_variants' => true,
            'variants' => [
                ['id' => $other->id, 'name' => $other->name, 'sku' => $other->sku, 'is_default' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.default_variant_id', $other->id);

    expect($default->fresh()->trashed())->toBeTrue()
        ->and((bool) $other->fresh()->is_default)->toBeTrue();
});

test('removing every variant leaves the product with a generated default', function (): void {
    $product = Product::factory()->create(['sku' => 'EMPTIED']);
    $only = ProductVariant::factory()->default()->create(['product_id' => $product->id]);

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$product->id, [
            'replace_variants' => false,
            'remove_missing_variants' => true,
            'variants' => [],
        ])
        ->assertOk();

    expect($only->fresh()->trashed())->toBeTrue()
        ->and($product->variants()->sole()->sku)->toBe('EMPTIED-DEFAULT');
});

test('a failure while creating the default variant rolls the whole product back', function (): void {
    $this->mock(ProductDefaultVariantService::class, function ($mock): void {
        $mock->shouldReceive('ensureDefault')->andThrow(new RuntimeException('boom'));
    });

    defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload([
            'variants' => [variantRow('LAPTOP-ROLLBACK')],
        ]))
        ->assertServerError();

    expect(Product::withTrashed()->count())->toBe(0)
        ->and(ProductVariant::withTrashed()->count())->toBe(0);
});

test('saving a product keeps its variants serials, stock history and cart lines attached', function (): void {
    $id = defaultVariantAdmin()
        ->postJson('/api/v1/admin/products', defaultVariantPayload())
        ->assertCreated()
        ->json('data.id');
    $product = Product::findOrFail($id);
    $variant = $product->variants()->sole();

    app(ProductSerialService::class)->receive(['SN-KEEP-1'], (string) $product->id, $variant->id);
    $cartItem = CartItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id]);
    $movements = $variant->stockMovements()->count();

    defaultVariantAdmin()
        ->putJson('/api/v1/admin/products/'.$id, [
            'name' => 'Laptop Renamed',
            'replace_variants' => false,
            'remove_missing_variants' => true,
            'variants' => [
                ['id' => $variant->id, 'name' => 'Standard', 'sku' => $variant->sku, 'is_default' => true],
            ],
        ])
        ->assertOk();

    expect($product->variants()->pluck('id')->all())->toBe([$variant->id])
        ->and(ProductSerial::where('serial_number', 'SN-KEEP-1')->value('product_variant_id'))->toBe($variant->id)
        ->and($cartItem->fresh()->product_variant_id)->toBe($variant->id)
        ->and($variant->stockMovements()->count())->toBe($movements);
});
