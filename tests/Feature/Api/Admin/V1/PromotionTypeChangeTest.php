<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Switching a promotion between `fixed` and `percentage` changes what its
 * value means, so the switch must carry a new value, and the storefront must
 * show only the figure that matches the new type.
 */
function typeChangeAdmin(): TestCase
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));
    $token = (new AdminAuthService)->createAdminToken(User::factory()->superAdmin()->create())['access_token'];

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

function typeChangePromotion(Product $product, array $attributes = []): Coupon
{
    $promotion = Coupon::create(array_merge([
        'name' => 'Flat Discount',
        'code' => 'FLAT-'.fake()->unique()->numerify('####'),
        'type' => 'fixed',
        'value' => 15,
        'target_type' => Coupon::TARGET_PRODUCTS,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ], $attributes));
    $promotion->products()->attach($product->id);

    return $promotion;
}

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
    $this->product = Product::factory()->create(['is_active' => true, 'price' => 599, 'sale_price' => null]);
});

test('switching fixed to percentage shows only the percentage on the storefront', function (): void {
    $promotion = typeChangePromotion($this->product);

    $this->getJson("/api/v1/products/{$this->product->id}")
        ->assertJsonPath('data.promotion.discount_amount', 15)
        ->assertJsonPath('data.promotion.discount_percentage', null);

    typeChangeAdmin()->putJson("/api/v1/admin/promotions/{$promotion->id}", ['type' => 'percentage', 'value' => 10])
        ->assertOk()
        ->assertJsonPath('data.type', 'percentage');

    $this->getJson("/api/v1/products/{$this->product->id}")
        ->assertJsonPath('data.promotion.type', 'percentage')
        ->assertJsonPath('data.promotion.discount_percentage', 10)
        ->assertJsonPath('data.promotion.discount_amount', null)
        ->assertJsonPath('data.promotion.final_price', 539.1);
});

test('switching percentage to fixed shows only the amount on the storefront', function (): void {
    $promotion = typeChangePromotion($this->product, ['type' => 'percentage', 'value' => 10]);

    typeChangeAdmin()->putJson("/api/v1/admin/promotions/{$promotion->id}", ['type' => 'fixed', 'value' => 50])
        ->assertOk()
        ->assertJsonPath('data.type', 'fixed');

    $this->getJson("/api/v1/products/{$this->product->id}")
        ->assertJsonPath('data.promotion.type', 'fixed')
        ->assertJsonPath('data.promotion.discount_amount', 50)
        ->assertJsonPath('data.promotion.discount_percentage', null)
        ->assertJsonPath('data.promotion.final_price', 549);
});

test('a type change without a new value is rejected and keeps the promotion as it was', function (): void {
    $promotion = typeChangePromotion($this->product, ['value' => 150]);

    typeChangeAdmin()->putJson("/api/v1/admin/promotions/{$promotion->id}", ['type' => 'percentage'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value' => 'Enter the value for the new promotion type.']);

    expect($promotion->fresh())->type->toBe('fixed')->value->toEqual(150);
});

test('resending the same type does not require a value', function (): void {
    $promotion = typeChangePromotion($this->product);

    typeChangeAdmin()->putJson("/api/v1/admin/promotions/{$promotion->id}", ['type' => 'fixed', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

test('a percentage above 100 is rejected on create and update', function (): void {
    $admin = typeChangeAdmin();

    $admin->postJson('/api/v1/admin/promotions', [
        'name' => 'Too Much',
        'code' => 'TOOMUCH1',
        'type' => 'percentage',
        'value' => 120,
        'starts_at' => now()->subDay()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['value' => 'A percentage promotion cannot exceed 100%.']);

    $fixed = typeChangePromotion($this->product, ['value' => 150]);
    $admin->putJson("/api/v1/admin/promotions/{$fixed->id}", ['type' => 'percentage', 'value' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    $percentage = typeChangePromotion($this->product, ['type' => 'percentage', 'value' => 10]);
    $admin->putJson("/api/v1/admin/promotions/{$percentage->id}", ['value' => 101])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    // A fixed amount above 100 is still a valid money amount.
    $admin->putJson("/api/v1/admin/promotions/{$fixed->id}", ['value' => 250])->assertOk();
});
