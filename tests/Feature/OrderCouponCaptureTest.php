<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
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

test('checkout charges a variant sale price instead of its regular price', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $product = Product::factory()->create(['price' => 100]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Large',
        'slug' => 'large-'.uniqid(),
        'sku' => 'LARGE-'.uniqid(),
        'price' => 120,
        'sale_price' => 90,
    ]);
    $shipping = ShippingMethod::create([
        'name' => 'Standard', 'code' => 'std', 'base_price' => 0, 'is_active' => true, 'type' => 'flat',
    ]);
    $headers = clientHeaders($user);

    $cartId = $this->withHeaders($headers)
        ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', 90)
        ->json('data.id');

    $orderId = $this->withHeaders($headers)
        ->postJson('/api/v1/orders', [
            'cart_id' => $cartId,
            'shipping_method_id' => $shipping->id,
            'recipient_name' => 'Dara Sok',
            'address_line_1' => '1 Main St',
            'city' => 'Phnom Penh',
            'postal_code' => '12000',
            'country' => 'KH',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 180)
        ->assertJsonPath('data.grand_total', 180)
        ->json('data.id');

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_variant_id' => $variant->id,
        'unit_price' => 90,
        'sale_price' => 90,
        'subtotal' => 180,
        'total' => 180,
    ]);
});

test('admin orders reject product promotions below their minimum spend', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();
    $product = Product::factory()->create(['price' => 34]);
    $promotion = Coupon::create([
        'name' => 'Custom PC Build Promo',
        'code' => 'PCBUILD20',
        'type' => 'percentage',
        'kind' => 'bundle',
        'value' => 20,
        'min_order_amount' => 1000,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);
    $product->promotions()->attach($promotion->id);
    $headers = adminHeaders($admin);

    $items = [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 34]];
    $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders/preview', ['items' => $items])
        ->assertOk()
        ->assertJsonPath('data.items.0.discount', '0.00')
        ->assertJsonPath('data.items.0.total', '34.00')
        ->assertJsonPath('data.promotion_discount', '0.00')
        ->assertJsonPath('data.total', '34.00');

    $orderId = $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $customer->id,
            'payment_method' => 'cod',
            'items' => $items,
        ])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', '34.00')
        ->assertJsonPath('data.discount_total', '0.00')
        ->assertJsonPath('data.total', '34.00')
        ->json('data.id');

    $this->assertDatabaseHas('order_items', [
        'order_id' => Order::where('uuid', $orderId)->value('id'),
        'product_id' => $product->id,
        'discount' => 0,
        'total' => 34,
    ]);
});

test('promotion eligibility combines product and category targets with customer groups', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();
    $selectedGroup = CustomerGroup::create(['name' => 'Early Access', 'slug' => 'early-access', 'is_active' => true]);
    $otherGroup = CustomerGroup::create(['name' => 'Partners', 'slug' => 'partners', 'is_active' => true]);
    $customer->customerGroups()->attach([$selectedGroup->id, $otherGroup->id]);

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $product = Product::factory()->create(['category_id' => $child->id, 'price' => 100]);
    $categoryPromotion = Coupon::create([
        'name' => 'Category group offer', 'code' => 'CATGROUP', 'type' => 'percentage', 'value' => 20,
        'is_active' => true, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
        'include_subcategories' => true,
    ]);
    $categoryPromotion->categories()->attach($parent->id);
    $categoryPromotion->customerGroups()->attach($selectedGroup->id);

    $productPromotion = Coupon::create([
        'name' => 'Product offer', 'code' => 'PRODUCT15', 'type' => 'percentage', 'value' => 15,
        'is_active' => true, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
    ]);
    $productPromotion->products()->attach($product->id);

    $groupRestrictedProductPromotion = Coupon::create([
        'name' => 'Wrong group offer', 'code' => 'WRONGGROUP', 'type' => 'percentage', 'value' => 90,
        'is_active' => true, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
    ]);
    $groupRestrictedProductPromotion->products()->attach($product->id);
    $groupRestrictedProductPromotion->customerGroups()->attach(CustomerGroup::create([
        'name' => 'Wholesale', 'slug' => 'wholesale', 'is_active' => true,
    ])->id);

    $this->withHeaders(adminHeaders($admin))
        ->postJson('/api/v1/admin/orders/preview', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100]],
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0.discount', '20.00')
        ->assertJsonPath('data.items.0.promotion.code', 'CATGROUP');
});

test('a variant-targeted promotion applies only to its selected variant', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $product = Product::factory()->create(['price' => 100]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Configured model',
        'slug' => 'configured-model',
        'sku' => 'PROMO-VARIANT',
        'price' => 100,
        'is_active' => true,
    ]);
    $promotion = Coupon::create([
        'name' => 'Variant offer', 'code' => 'VARIANT20', 'type' => 'percentage', 'value' => 20,
        'is_active' => true, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
    ]);
    $promotion->variants()->attach($variant->id);

    $this->withHeaders(adminHeaders($admin))
        ->postJson('/api/v1/admin/orders/preview', [
            'items' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'qty' => 1, 'unit_price' => 100]],
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0.discount', '20.00')
        ->assertJsonPath('data.items.0.promotion.code', 'VARIANT20');
});

test('VIP customers receive five percent only on products without an applicable promotion', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();
    $customer->customerGroups()->attach(CustomerGroup::create([
        'name' => 'VIP',
        'slug' => CustomerGroup::VIP_SLUG,
        'is_active' => true,
    ])->id);

    $vipProduct = Product::factory()->create(['price' => 34]);
    $promotedProduct = Product::factory()->create(['price' => 100]);
    $promotion = Coupon::create([
        'name' => 'Selected product promo',
        'code' => 'VIP-PROMO',
        'type' => 'percentage',
        'value' => 20,
        'is_active' => true,
    ]);
    $promotedProduct->promotions()->attach($promotion->id);
    $items = [
        ['product_id' => $vipProduct->id, 'qty' => 1, 'unit_price' => 34],
        ['product_id' => $promotedProduct->id, 'qty' => 1, 'unit_price' => 100],
    ];
    $headers = adminHeaders($admin);

    $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders/preview', [
            'customer_id' => $customer->id,
            'items' => $items,
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0.discount', '1.70')
        ->assertJsonPath('data.items.0.discount_source', 'vip')
        ->assertJsonPath('data.items.0.promotion.name', 'VIP Discount')
        ->assertJsonPath('data.items.1.discount', '20.00')
        ->assertJsonPath('data.items.1.discount_source', 'promotion')
        ->assertJsonPath('data.promotion_discount', '21.70')
        ->assertJsonPath('data.total', '112.30');

    $orderId = $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $customer->id,
            'payment_method' => 'cod',
            'items' => $items,
        ])
        ->assertCreated()
        ->json('data.id');

    $order = Order::where('uuid', $orderId)->firstOrFail();
    expect(round((float) $order->discount_total, 2))->toBe(21.7)
        ->and(round((float) $order->grand_total, 2))->toBe(112.3);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $vipProduct->id,
        'discount' => 1.7,
        'total' => 32.3,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $promotedProduct->id,
        'discount' => 20,
        'total' => 80,
    ]);

    expect($order->items()->where('product_id', $vipProduct->id)->value('metadata')['promotion']['source'])
        ->toBe('vip');
});

test('admin order preview and creation calculate tax from store settings', function (): void {
    Setting::putStore('taxes.prices_include_tax', false, 'settings');
    Setting::putStore('taxes.rates', [[
        'region' => 'Cambodia',
        'name' => 'Standard VAT',
        'rate' => 10,
        'applies_to' => 'All goods',
    ]], 'settings');

    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();
    $product = Product::factory()->create(['price' => 100]);
    $items = [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100]];
    $headers = adminHeaders($admin);

    $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders/preview', [
            'customer_id' => $customer->id,
            'items' => $items,
        ])
        ->assertOk()
        ->assertJsonPath('data.tax_total', '10.00')
        ->assertJsonPath('data.tax_settings.label', 'Standard VAT')
        ->assertJsonPath('data.total', '110.00');

    $orderId = $this->withHeaders($headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $customer->id,
            'payment_method' => 'cod',
            'tax_total' => 1,
            'items' => $items,
        ])
        ->assertCreated()
        ->assertJsonPath('data.tax_total', '10.00')
        ->assertJsonPath('data.total', '110.00')
        ->json('data.id');

    $order = Order::where('uuid', $orderId)->firstOrFail();
    expect(round((float) $order->tax_total, 2))->toBe(10.0)
        ->and(round((float) $order->grand_total, 2))->toBe(110.0);
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
