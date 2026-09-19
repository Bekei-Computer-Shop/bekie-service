<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApiToken $token;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = ApiToken::factory()->for($this->user)->create(['scope' => 'client']);
        $this->cart = Cart::factory()->for($this->user)->create([
            'subtotal' => 100,
            'discount_total' => 0,
        ]);
    }

    public function test_apply_percentage_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
            'min_order_amount' => 50,
            'max_discount_amount' => null,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'SAVE20',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.coupon_code', 'SAVE20');
    }

    public function test_apply_fixed_discount_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'type' => 'fixed',
            'value' => 10,
            'min_order_amount' => 50,
            'max_discount_amount' => 50,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'SAVE10',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.coupon_code', 'SAVE10');
    }

    public function test_apply_coupon_requires_minimum_order(): void
    {
        $cart = Cart::factory()->for($this->user)->create(['subtotal' => 30]);
        Coupon::create([
            'code' => 'EXPENSIVE',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 50,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'EXPENSIVE',
                'cart_id' => $cart->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_apply_invalid_coupon(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'INVALID123',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_apply_expired_coupon(): void
    {
        Coupon::create([
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'EXPIRED',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_apply_inactive_coupon(): void
    {
        Coupon::create([
            'code' => 'INACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'INACTIVE',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_apply_exhausted_coupon(): void
    {
        Coupon::create([
            'code' => 'EXHAUSTED',
            'type' => 'percentage',
            'value' => 10,
            'usage_limit' => 5,
            'used_count' => 5,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'EXHAUSTED',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_apply_coupon_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/coupons/apply', [
            'code' => 'SAVE20',
            'cart_id' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_apply_coupon_to_invalid_cart(): void
    {
        Coupon::create([
            'code' => 'VALID',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/coupons/apply', [
                'code' => 'VALID',
                'cart_id' => 9999,
            ]);

        $response->assertUnprocessable();
    }
}
