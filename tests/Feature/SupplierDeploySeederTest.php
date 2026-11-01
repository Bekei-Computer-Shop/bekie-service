<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Supplier;
use Database\Seeders\SupplierDeploySeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a fresh database gets the starter suppliers once, with no store default', function (): void {
    (new SupplierDeploySeeder)->run();
    (new SupplierDeploySeeder)->run();

    expect(Supplier::count())->toBe(count(SupplierSeeder::SUPPLIERS))
        ->and(Supplier::where('is_default', true)->exists())->toBeFalse();
});

test('a deploy never re-adds or changes suppliers once any exist', function (): void {
    (new SupplierDeploySeeder)->run();

    Supplier::where('code', 'SUP-001')->update(['name' => 'Renamed by admin']);
    Supplier::where('code', 'SUP-002')->firstOrFail()->delete();

    (new SupplierDeploySeeder)->run();

    expect(Supplier::where('code', 'SUP-001')->value('name'))->toBe('Renamed by admin')
        ->and(Supplier::where('code', 'SUP-002')->exists())->toBeFalse()
        ->and(Supplier::withTrashed()->count())->toBe(count(SupplierSeeder::SUPPLIERS));
});

test('an environment where admins already entered suppliers is left alone', function (): void {
    $real = Supplier::factory()->create(['name' => 'Our Real Supplier', 'is_default' => true]);

    (new SupplierDeploySeeder)->run();

    expect(Supplier::count())->toBe(1)
        ->and(Supplier::sole()->is($real))->toBeTrue();
});

test('products are never given a default supplier', function (): void {
    $product = Product::factory()->create(['default_supplier_id' => null]);

    (new SupplierDeploySeeder)->run();

    expect($product->fresh()->default_supplier_id)->toBeNull();
});

test('it runs in production, unlike the demo SupplierSeeder', function (): void {
    app()->detectEnvironment(fn () => 'production');

    (new SupplierDeploySeeder)->run();

    expect(Supplier::count())->toBe(count(SupplierSeeder::SUPPLIERS));
});
