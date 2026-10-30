<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AdminAuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);

    $this->admin = User::factory()->superAdmin()->create();
    $this->customer = User::factory()->create();

    app()->instance('request', Request::create('/admin/auth/login', 'POST'));
    $this->headers = ['Authorization' => 'Bearer '.(new AdminAuthService)->createAdminToken($this->admin)['access_token']];

    // 5.00 + 1.50 per kg, up to 10 kg.
    $this->courier = ShippingMethod::factory()->create([
        'name' => 'Courier', 'code' => 'courier', 'base_price' => 5, 'price_per_kg' => 1.5,
        'min_weight' => null, 'max_weight' => 10, 'sort_order' => 1,
    ]);
    $this->pickup = ShippingMethod::factory()->create([
        'name' => 'Store pickup', 'code' => 'pickup', 'base_price' => 0, 'price_per_kg' => null,
        'min_weight' => null, 'max_weight' => null, 'sort_order' => 2,
    ]);
    $this->retired = ShippingMethod::factory()->inactive()->create(['code' => 'retired']);
});

test('the create-order form lists active shipping methods at their base price', function (): void {
    $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/orders/shipping-methods')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'courier')
        ->assertJsonPath('data.0.fee', '5.00')
        ->assertJsonPath('data.0.available', true)
        ->assertJsonPath('data.1.code', 'pickup');
});

test('preview prices shipping from the lines weight and adds it to the total', function (): void {
    $product = Product::factory()->create(['price' => 100, 'weight' => 2]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders/preview', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'qty' => 3, 'unit_price' => 100]],
        ])
        ->assertOk()
        ->assertJsonPath('data.shipping_weight', '6.00')
        ->assertJsonPath('data.shipping_method.id', $this->courier->id)
        ->assertJsonPath('data.shipping_total', '14.00')
        ->assertJsonPath('data.shipping_options.0.fee', '14.00')
        ->assertJsonPath('data.shipping_options.1.fee', '0.00')
        ->assertJsonPath('data.total', '314.00');
});

test('preview marks a method that cannot carry the weight and charges nothing for it', function (): void {
    $product = Product::factory()->create(['price' => 10, 'weight' => 6]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders/preview', [
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price' => 10]],
        ])
        ->assertOk()
        ->assertJsonPath('data.shipping_options.0.available', false)
        ->assertJsonPath('data.shipping_method', null)
        ->assertJsonPath('data.shipping_total', '0.00')
        ->assertJsonPath('data.total', '20.00');
});

test('an order is saved with the method, its server-side fee and the grand total', function (): void {
    $product = Product::factory()->create(['price' => 100, 'weight' => 2]);

    $orderId = $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'payment_method' => 'cod',
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'qty' => 3, 'unit_price' => 100]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', '300.00')
        ->assertJsonPath('data.shipping_total', '14.00')
        ->assertJsonPath('data.shipping_method.id', $this->courier->id)
        ->assertJsonPath('data.shipping_method.name', 'Courier')
        ->assertJsonPath('data.shipping_method.fee', '14.00')
        ->assertJsonPath('data.tracking.provider', 'Courier')
        ->assertJsonPath('data.total', '314.00')
        ->json('data.id');

    $order = Order::where('uuid', $orderId)->firstOrFail();
    expect($order->shipping_method_id)->toBe($this->courier->id)
        ->and($order->shipping_provider)->toBe('Courier')
        ->and(round((float) $order->shipping_total, 2))->toBe(14.0)
        ->and(round((float) $order->grand_total, 2))->toBe(314.0);
});

test('a variant line is weighed by the variant, not its product', function (): void {
    $product = Product::factory()->create(['price' => 100, 'weight' => 9]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Light model',
        'slug' => 'light-model',
        'sku' => 'SHIP-VARIANT',
        'price' => 100,
        'weight' => 1,
        'is_active' => true,
    ]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'qty' => 2, 'unit_price' => 100]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.shipping_total', '8.00')
        ->assertJsonPath('data.total', '208.00');
});

test('an inactive shipping method is rejected', function (): void {
    $product = Product::factory()->create(['price' => 10, 'weight' => 1]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->retired->id,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 10]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shipping_method_id');

    expect(Order::count())->toBe(0);
});

test('an unknown shipping method is rejected', function (): void {
    $product = Product::factory()->create(['price' => 10]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => 999999,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 10]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shipping_method_id');
});

test('a method that cannot carry the order weight is rejected', function (): void {
    $product = Product::factory()->create(['price' => 10, 'weight' => 6]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price' => 10]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shipping_method_id');

    expect(Order::count())->toBe(0);
});

test('a shipping fee cannot be sent alongside a shipping method', function (): void {
    $product = Product::factory()->create(['price' => 10, 'weight' => 1]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->courier->id,
            'shipping_total' => 0,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 10]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shipping_total');
});

test('an order without a shipping method still saves as before', function (): void {
    $product = Product::factory()->create(['price' => 10]);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_total' => 3,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 10]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.shipping_method', null)
        ->assertJsonPath('data.shipping_total', '3.00')
        ->assertJsonPath('data.total', '13.00');
});

test('an order keeps the shipping name and fee it was placed with', function (): void {
    $product = Product::factory()->create(['price' => 100, 'weight' => 2]);

    $orderId = $this->withHeaders($this->headers)
        ->postJson('/api/v1/admin/orders', [
            'customer_id' => $this->customer->id,
            'shipping_method_id' => $this->courier->id,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 100]],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->courier->update(['name' => 'Courier Plus', 'base_price' => 50]);
    $this->courier->delete();

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/admin/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.shipping_method.name', 'Courier')
        ->assertJsonPath('data.shipping_method.fee', '8.00')
        ->assertJsonPath('data.total', '108.00');
});
