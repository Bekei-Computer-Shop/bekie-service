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

test('a variant takes one serial number and refuses a second', function (): void {
    $variant = singleSerialVariant();
    $service = app(ProductSerialService::class);

    $service->receive(['SN-ONE'], (string) $variant->product_id, $variant->id);

    expect(fn () => $service->receive(['SN-TWO'], (string) $variant->product_id, $variant->id))
        ->toThrow(InvalidArgumentException::class, 'already has a serial number');
    expect(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(1);
});

test('a batch of several serials is refused for one variant', function (): void {
    $variant = singleSerialVariant();

    expect(fn () => app(ProductSerialService::class)->receive(['SN-A', 'SN-B'], (string) $variant->product_id, $variant->id))
        ->toThrow(InvalidArgumentException::class, 'only 1 serial number');
    expect(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(0);
});

test('a cancelled serial no longer holds the variant', function (): void {
    $variant = singleSerialVariant();
    $service = app(ProductSerialService::class);

    $service->receive(['SN-UNDONE'], (string) $variant->product_id, $variant->id);
    ProductSerial::where('serial_number', 'SN-UNDONE')->update(['status' => ProductSerial::CANCELLED]);

    expect($service->receive(['SN-AGAIN'], (string) $variant->product_id, $variant->id))->toHaveCount(1);
});

test('stock in on a variant that already has stock needs just the one serial', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);

    stockInVariant($variant, 1, ['SN-FIRST'])->assertCreated();

    expect($variant->fresh()->stock_quantity)->toBe(6)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->pluck('serial_number')->all())->toBe(['SN-FIRST']);
});

test('stock in of several units still takes only the one serial', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);

    stockInVariant($variant, 3, ['SN-BULK'])->assertCreated();

    expect($variant->fresh()->stock_quantity)->toBe(8)
        ->and(ProductSerial::where('product_variant_id', $variant->id)->count())->toBe(1);
});

test('stock in on a variant that already has its serial needs none', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);
    app(ProductSerialService::class)->receive(['SN-HELD'], (string) $variant->product_id, $variant->id);

    stockInVariant($variant, 2, [])->assertCreated();

    expect($variant->fresh()->stock_quantity)->toBe(7);
});

test('stock in refuses a second serial for a variant that already has one', function (): void {
    singleSerialAdmin();
    $variant = singleSerialVariant(stock: 5);
    app(ProductSerialService::class)->receive(['SN-HELD'], (string) $variant->product_id, $variant->id);

    stockInVariant($variant, 1, ['SN-EXTRA'])->assertUnprocessable();

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
