<?php

declare(strict_types=1);

use App\Models\ShippingMethod;
use Database\Seeders\ShippingMethodDeploySeeder;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a fresh database gets every default shipping method', function (): void {
    (new ShippingMethodDeploySeeder)->run();
    (new ShippingMethodDeploySeeder)->run();

    expect(ShippingMethod::count())->toBe(count(ShippingMethodSeeder::METHODS))
        ->and(ShippingMethod::active()->pluck('code')->all())
        ->toEqualCanonicalizing(array_column(ShippingMethodSeeder::METHODS, 'code'));
});

test('a deploy keeps what admins changed', function (): void {
    (new ShippingMethodDeploySeeder)->run();

    ShippingMethod::where('code', 'store_pickup')->update(['name' => 'Pickup at Branch', 'base_price' => 1.5]);
    ShippingMethod::where('code', 'economy_delivery')->update(['is_active' => false]);
    ShippingMethod::where('code', 'express_courier')->firstOrFail()->delete();

    (new ShippingMethodDeploySeeder)->run();

    $pickup = ShippingMethod::where('code', 'store_pickup')->firstOrFail();
    expect($pickup->name)->toBe('Pickup at Branch')
        ->and((float) $pickup->base_price)->toBe(1.5)
        ->and(ShippingMethod::where('code', 'economy_delivery')->value('is_active'))->toBeFalse()
        ->and(ShippingMethod::where('code', 'express_courier')->exists())->toBeFalse()
        ->and(ShippingMethod::withTrashed()->count())->toBe(count(ShippingMethodSeeder::METHODS));
});

test('only missing defaults are added next to methods admins created', function (): void {
    ShippingMethod::factory()->create(['code' => 'standard_courier', 'name' => 'Our Courier', 'base_price' => 3]);

    (new ShippingMethodDeploySeeder)->run();

    expect(ShippingMethod::where('code', 'standard_courier')->value('name'))->toBe('Our Courier')
        ->and(ShippingMethod::count())->toBe(count(ShippingMethodSeeder::METHODS));
});
