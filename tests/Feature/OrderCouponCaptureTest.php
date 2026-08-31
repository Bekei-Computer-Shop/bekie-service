<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\AuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

function clientHeaders(User $user): array
{
    $token = (new AuthService)->createToken($user, Request::create('/api/v1/auth/login', 'POST'))['access_token'];

    return ['Authorization' => 'Bearer '.$token];
}

function adminHeaders(User $user): array
{
    app()->instance('request', Request::create('/admin/auth/login', 'POST'));

    return ['Authorization' => 'Bearer '.(new AdminAuthService)->createAdminToken($user)['access_token']];
}

test('applying a coupon persists it on the cart and checkout stamps it onto the order', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $product = Product::factory()->create(['price' => 100]);
    $shipping = ShippingMethod::create([
        'name' => 'Standard', 'code' => 'std', 'base_price' => 5, 'is_active' => true, 'type' => 'flat',
    ]);
    $coupon = Coupon::create([
        'name' => 'Ten off', 'code' => 'SAVE10', 'type' => 'fixed', 'value' => 10, 'is_active' => true,
    ]);

    $cart = Cart::create(['user_id' => $user->id, 'currency' => 'USD', 'subtotal' => 100]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'total' => 100,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
    ]);

    $headers = clientHeaders($user);

    $this->withHeaders($headers)
        ->postJson('/api/v1/coupons/apply', ['cart_id' => $cart->id, 'code' => 'SAVE10'])
        ->assertOk();

    $this->assertDatabaseHas('carts', [
        'id' => $cart->id,
        'coupon_code' => 'SAVE10',
        'discount_total' => 10,
    ]);

    $order = $this->withHeaders($headers)
        ->postJson('/api/v1/orders', [
            'cart_id' => $cart->id,
            'shipping_method_id' => $shipping->id,
            'recipient_name' => 'Dara Sok',
            'address_line_1' => '1 Main St',
            'city' => 'Phnom Penh',
            'country' => 'KH',
        ])
        ->assertCreated()
        ->assertJsonPath('data.coupon_code', 'SAVE10')
        ->json('data.id');

    $this->assertDatabaseHas('orders', [
        'coupon_code' => 'SAVE10',
        'coupon_id' => $coupon->id,
        'discount_total' => 10,
    ]);

    $this->assertDatabaseHas('coupon_usages', [
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'coupon_code' => 'SAVE10',
        'discount_amount' => 10,
    ]);

    expect($coupon->fresh()->used_count)->toBe(1);
});

test('admin order detail returns the coupon block', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();

    $product = Product::factory()->create(['price' => 100]);
    $shipping = ShippingMethod::create([
        'name' => 'Standard', 'code' => 'std', 'base_price' => 0, 'is_active' => true, 'type' => 'flat',
    ]);
    $coupon = Coupon::create([
        'name' => 'Summer', 'code' => 'SUMMER20', 'type' => 'percentage', 'value' => 20, 'is_active' => true,
    ]);

    $cart = Cart::create(['user_id' => $customer->id, 'currency' => 'USD', 'subtotal' => 100]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'total' => 100,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
    ]);

    $customer->assignRole('user');
    $this->withHeaders(clientHeaders($customer))
        ->postJson('/api/v1/coupons/apply', ['cart_id' => $cart->id, 'code' => 'SUMMER20'])
        ->assertOk();

    $orderId = $this->withHeaders(clientHeaders($customer))
        ->postJson('/api/v1/orders', [
            'cart_id' => $cart->id,
            'shipping_method_id' => $shipping->id,
            'recipient_name' => 'Dara Sok',
            'address_line_1' => '1 Main St',
            'city' => 'Phnom Penh',
            'country' => 'KH',
        ])
        ->assertCreated()
        ->json('data.id');

    $orderUuid = Order::findOrFail($orderId)->uuid;

    $this->withHeaders(adminHeaders($admin))
        ->getJson("/api/v1/admin/orders/{$orderUuid}")
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'SUMMER20')
        ->assertJsonPath('data.coupon.name', 'Summer')
        ->assertJsonPath('data.coupon.type', 'percentage');
});

test('orders without a coupon expose a null coupon block', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();

    $product = Product::factory()->create(['price' => 50]);
    $shipping = ShippingMethod::create([
        'name' => 'Standard', 'code' => 'std', 'base_price' => 0, 'is_active' => true, 'type' => 'flat',
    ]);

    $cart = Cart::create(['user_id' => $customer->id, 'currency' => 'USD', 'subtotal' => 50]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 50,
        'subtotal' => 50,
        'total' => 50,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
    ]);

    $customer->assignRole('user');
    $orderId = $this->withHeaders(clientHeaders($customer))
        ->postJson('/api/v1/orders', [
            'cart_id' => $cart->id,
            'shipping_method_id' => $shipping->id,
            'recipient_name' => 'Dara Sok',
            'address_line_1' => '1 Main St',
            'city' => 'Phnom Penh',
            'country' => 'KH',
        ])
        ->assertCreated()
        ->json('data.id');

    $orderUuid = Order::findOrFail($orderId)->uuid;

    $this->withHeaders(adminHeaders($admin))
        ->getJson("/api/v1/admin/orders/{$orderUuid}")
        ->assertOk()
        ->assertJsonPath('data.coupon', null);

    $this->assertDatabaseCount('coupon_usages', 0);
});
