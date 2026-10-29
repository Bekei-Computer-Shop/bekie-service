<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\ProductSerialService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function singleSerialAdmin(): void
{
    test()->seed(AdminPermissionsSeeder::class);

    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);
    $token = (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];

    test()->withHeader('Authorization', 'Bearer '.$token);
}

function singleSerialVariant(int $stock = 5): ProductVariant
{
    $product = Product::factory()->create(['is_serialized' => true, 'track_inventory' => true]);

    return ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Citrus',
        'slug' => 'citrus-'.uniqid(),
        'sku' => 'CITRUS-'.strtoupper(uniqid()),
        'price' => 10,
        'stock_quantity' => $stock,
        'track_inventory' => true,
        'is_serialized' => true,
    ]);
}

function stockInVariant(ProductVariant $variant, int $quantity, array $serials)
{
    return test()->postJson('/api/v1/admin/stock/movements/bulk', [
        'items' => [[
            'stockable_type' => 'variant',
            'stockable_id' => (string) $variant->id,
            'movement_type' => 'stock_in',
            'quantity' => $quantity,
            'reason' => 'Supplier Delivery',
            'serial_numbers' => $serials,
        ]],
    ]);
}

test('a variant can hold several serial numbers', function (): void {
    $variant = singleSerialVariant();
    $service = app(ProductSerialService::class);

    $service->receive(['SN-ONE'], (string) $variant->product_id, $variant->id);
    $service->receive(['SN-TWO', 'SN-THREE'], (string) $variant->product_id, $variant->id);

    expect(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(3);
});

test('a serial number can never be received twice', function (): void {
    $variant = singleSerialVariant();
    $service = app(ProductSerialService::class);

    $service->receive(['SN-ONE'], (string) $variant->product_id, $variant->id);

    expect(fn () => $service->receive(['sn-one'], (string) $variant->product_id, $variant->id))
        ->toThrow(InvalidArgumentException::class, 'already exists');
});

test('an admin can change a serial to another variant of the same product', function (): void {
    singleSerialAdmin();
    $currentVariant = singleSerialVariant();
    $newVariant = ProductVariant::create([
        'product_id' => $currentVariant->product_id,
        'name' => 'Berry',
        'slug' => 'berry-'.uniqid(),
        'sku' => 'BERRY-'.strtoupper(uniqid()),
        'price' => 12,
        'stock_quantity' => 5,
    ]);
    $serial = app(ProductSerialService::class)->receive(
        ['SN-CHANGE-VARIANT'],
        (string) $currentVariant->product_id,
        $currentVariant->id,
    )[0];

    $this->patchJson('/api/v1/admin/product-serials/'.$serial->id, [
        'product_variant_id' => $newVariant->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.variant.id', $newVariant->id)
        ->assertJsonPath('data.variant.name', 'Berry');

    expect($serial->fresh()->product_variant_id)->toBe($newVariant->id);

    $this->getJson('/api/v1/admin/product-serials/'.$serial->id)
        ->assertOk()
        ->assertJsonFragment([
            'id' => $newVariant->id,
            'name' => 'Berry',
            'sku' => $newVariant->sku,
        ]);
});

test('an admin cannot change a serial to a variant from another product', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant();
    $otherVariant = singleSerialVariant();
    $serial = app(ProductSerialService::class)->receive(
        ['SN-REJECT-VARIANT'],
        (string) $variant->product_id,
        $variant->id,
    )[0];

    $this->patchJson('/api/v1/admin/product-serials/'.$serial->id, [
        'product_variant_id' => $otherVariant->id,
    ])->assertUnprocessable();

    expect($serial->fresh()->product_variant_id)->toBe($variant->id);
});

test('stock in needs one serial number per unit added', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);

    stockInVariant($variant, 3, ['SN-A', 'SN-B', 'SN-C'])->assertCreated();

    expect($variant->fresh()->stock_quantity)->toBe(8)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->pluck('serial_number')->all())
        ->toEqualCanonicalizing(['SN-A', 'SN-B', 'SN-C']);
});

test('a variant that already has a serial still needs one per unit added', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 0);
    app(ProductSerialService::class)->receive(['SN-HELD'], (string) $variant->product_id, $variant->id);

    stockInVariant($variant, 3, ['SN-N1', 'SN-N2', 'SN-N3'])->assertCreated();

    expect($variant->fresh()->stock_quantity)->toBe(3)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(4);
});

test('stock in is refused when there are fewer serials than units', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);

    stockInVariant($variant, 3, ['SN-ONLY'])->assertUnprocessable();
    stockInVariant($variant, 3, [])->assertUnprocessable();

    expect($variant->fresh()->stock_quantity)->toBe(5)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(0);
});

test('stock in is refused when there are more serials than units', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);

    stockInVariant($variant, 1, ['SN-X', 'SN-Y'])->assertUnprocessable();

    expect($variant->fresh()->stock_quantity)->toBe(5)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(0);
});

test('stock in refuses a serial the variant already holds, and changes nothing', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);
    app(ProductSerialService::class)->receive(['SN-HELD'], (string) $variant->product_id, $variant->id);

    stockInVariant($variant, 2, ['sn-held', 'SN-NEW'])->assertUnprocessable();

    expect($variant->fresh()->stock_quantity)->toBe(5)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(1);
});

test('the stock catalog reports which variants already hold a serial', function (): void {
    singleSerialAdmin();
    $held = singleSerialVariant();
    $empty = ProductVariant::create([
        'product_id' => $held->product_id,
        'name' => 'Silver',
        'slug' => 'silver-'.uniqid(),
        'sku' => 'SILVER-'.strtoupper(uniqid()),
        'price' => 10,
        'stock_quantity' => 0,
        'track_inventory' => true,
        'is_serialized' => true,
    ]);
    app(ProductSerialService::class)->receive(['SN-HELD'], (string) $held->product_id, $held->id);

    $variants = collect(
        test()->getJson('/api/v1/admin/stock?include_variants=1')->assertOk()->json('data.items.0.variants'),
    )->pluck('serials_count', 'id');

    expect($variants[$held->id])->toBe(1)
        ->and($variants[$empty->id])->toBe(0);
});
