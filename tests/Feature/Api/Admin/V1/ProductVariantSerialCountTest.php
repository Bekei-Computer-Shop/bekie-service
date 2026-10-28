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

function serialCountAdminToken(): string
{
    $request = Request::create('/admin/auth/login', 'POST');
    app()->instance('request', $request);

    return (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];
}

function serialCountVariant(Product $product, string $sku): ProductVariant
{
    return ProductVariant::create([
        'product_id' => $product->id,
        'name' => $sku,
        'slug' => strtolower($sku),
        'sku' => $sku,
        'price' => 10,
        'stock_quantity' => 0,
    ]);
}

test('product detail reports how many live serials each variant already holds', function (): void {
    $this->seed(AdminPermissionsSeeder::class);

    $product = Product::factory()->create(['is_serialized' => true]);
    $withSerial = serialCountVariant($product, 'WITH-SERIAL');
    $cancelled = serialCountVariant($product, 'CANCELLED-SERIAL');
    $empty = serialCountVariant($product, 'NO-SERIAL');

    $service = app(ProductSerialService::class);
    $service->receive(['SN-LIVE'], (string) $product->id, $withSerial->id);
    $service->receive(['SN-UNDONE'], (string) $product->id, $cancelled->id);
    ProductSerial::where('serial_number', 'SN-UNDONE')->update(['status' => ProductSerial::CANCELLED]);

    $variants = collect(
        $this->withHeader('Authorization', 'Bearer '.serialCountAdminToken())
            ->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->json('data.variants'),
    )->pluck('serials_count', 'sku');

    expect($variants['WITH-SERIAL'])->toBe(1)
        ->and($variants['CANCELLED-SERIAL'])->toBe(0)
        ->and($variants['NO-SERIAL'])->toBe(0);
});
