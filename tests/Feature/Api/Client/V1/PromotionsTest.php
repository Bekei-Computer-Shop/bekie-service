<?php

declare(strict_types=1);

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The public storefront promotions endpoint lists the live `coupons` rows the
 * admin manages under "Promotions" — and only the ones that can actually be
 * used right now.
 */
function clientPromotion(array $attributes = []): Coupon
{
    return Coupon::create(array_merge([
        'name' => 'Summer Sale',
        'code' => 'SUMMER20',
        'type' => 'percentage',
        'value' => 20,
        'description' => 'Seasonal promotion',
        'banner_image' => null,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addWeek(),
        'usage_limit' => 100,
        'used_count' => 5,
        'user_limit' => 1,
        'min_order_amount' => 50,
        'max_discount_amount' => 200,
        'applicable_products' => null,
        'applicable_categories' => null,
        'is_active' => true,
    ], $attributes));
}

test('client promotions only lists active, in-window, not-exhausted coupons', function (): void {
    clientPromotion(['name' => 'Live', 'code' => 'LIVE10']);
    clientPromotion(['name' => 'Paused', 'code' => 'PAUSED01', 'is_active' => false]);
    clientPromotion(['name' => 'Not started', 'code' => 'FUTURE01', 'starts_at' => now()->addDay()]);
    clientPromotion(['name' => 'Expired', 'code' => 'GONE0001', 'expires_at' => now()->subDay()]);
    clientPromotion(['name' => 'Exhausted', 'code' => 'SOLD0001', 'usage_limit' => 10, 'used_count' => 10]);

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'LIVE10');
});

test('the promotion payload carries display fields and hides the redemption ledger', function (): void {
    clientPromotion([
        'name' => 'VIP Fixed',
        'code' => 'VIP100',
        'type' => 'fixed',
        'value' => 100,
        'banner_image' => 'https://cdn.example/promo.jpg',
        'min_order_amount' => 500,
        'max_discount_amount' => 100,
        'usage_limit' => 50,
        'used_count' => 5,
    ]);

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'VIP Fixed')
        ->assertJsonPath('data.0.code', 'VIP100')
        ->assertJsonPath('data.0.type', 'fixed')
        ->assertJsonPath('data.0.value', 100)
        ->assertJsonPath('data.0.min_order_amount', 500)
        ->assertJsonPath('data.0.max_discount_amount', 100)
        ->assertJsonPath('data.0.banner_image', 'https://cdn.example/promo.jpg')
        ->assertJsonPath('data.0.usage_remaining', 45)
        ->assertJsonMissingPath('data.0.used_count')
        ->assertJsonMissingPath('data.0.usage_limit');
});

test('promotions with no usage cap report a null usage_remaining', function (): void {
    clientPromotion(['name' => 'Unlimited', 'code' => 'OPEN0001', 'usage_limit' => null, 'used_count' => 3]);

    $this->getJson('/api/v1/promotions')
        ->assertOk()
        ->assertJsonPath('data.0.usage_remaining', null);
});