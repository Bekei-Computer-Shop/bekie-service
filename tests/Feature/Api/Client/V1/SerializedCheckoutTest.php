<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('user');
    $token = (new AuthService)->createToken($this->customer, Request::create('/api/v1/auth/login', 'POST'))['access_token'];
    $this->headers = ['Authorization' => 'Bearer '.$token];

    $this->shipping = ShippingMethod::create([
        'name' => 'Pickup', 'code' => 'pickup', 'base_price' => 0, 'is_active' => true, 'type' => 'pickup',
    ]);
    $this->laptop = Product::factory()->create(['price' => 500, 'is_serialized' => true]);
    $this->cart = Cart::create(['user_id' => $this->customer->id, 'currency' => 'USD']);
});

function serializedCartLine(Cart $cart, Product $product, int $quantity, ?ProductVariant $variant = null): void
{
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant?->id,
        'quantity' => $quantity,
        'unit_price' => 500,
        'subtotal' => 500 * $quantity,
        'total' => 500 * $quantity,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
    ]);
}

function stockSerials(Product $product, array $numbers, ?ProductVariant $variant = null): void
{
    foreach ($numbers as $number) {
        ProductSerial::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'serial_number' => $number,
            'status' => ProductSerial::AVAILABLE,
        ]);
    }
}

/** The storefront's checkout body: an inline address and no serial numbers. */
function storefrontCheckout(Cart $cart, ShippingMethod $shipping, array $extra = []): array
{
    return [
        'cart_id' => $cart->id,
        'shipping_method_id' => $shipping->id,
        'recipient_name' => 'Dara Sok',
        'phone' => '012345678',
        'address_line_1' => '1 Main St',
        'city' => 'Phnom Penh',
        'postal_code' => '12000',
        'country' => 'KH',
        'payment_method' => 'cod',
        ...$extra,
    ];
}

test('checkout reserves the oldest available units when no serials are sent', function (): void {
    stockSerials($this->laptop, ['SN-001', 'SN-002', 'SN-003']);
    serializedCartLine($this->cart, $this->laptop, 2);

    $orderId = $this->withHeaders($this->headers)
        ->postJson('/api/v1/orders', storefrontCheckout($this->cart, $this->shipping))
        ->assertCreated()
        ->json('data.id');

    $order = Order::where('uuid', $orderId)->orWhere('id', $orderId)->firstOrFail();
    expect(ProductSerial::where('order_id', $order->id)->orderBy('serial_number')->pluck('serial_number')->all())
        ->toBe(['SN-001', 'SN-002'])
        ->and(ProductSerial::where('serial_number', 'SN-001')->value('status'))->toBe(ProductSerial::RESERVED)
        ->and(ProductSerial::where('serial_number', 'SN-001')->value('customer_id'))->toBe($this->customer->id)
        ->and(ProductSerial::where('serial_number', 'SN-003')->value('status'))->toBe(ProductSerial::AVAILABLE);
});

test('the cart checkout endpoint reserves units the same way', function (): void {
    stockSerials($this->laptop, ['SN-001']);
    serializedCartLine($this->cart, $this->laptop, 1);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/cart/checkout', storefrontCheckout($this->cart, $this->shipping))
        ->assertCreated();

    expect(ProductSerial::where('serial_number', 'SN-001')->value('status'))->toBe(ProductSerial::RESERVED);
});

test('a variant line only takes units of that variant', function (): void {
    $variant = ProductVariant::create([
        'product_id' => $this->laptop->id, 'name' => '32GB', 'slug' => 'laptop-32gb', 'sku' => 'LAP-32',
        'price' => 500, 'is_active' => true, 'is_serialized' => true,
    ]);
    stockSerials($this->laptop, ['SN-BASE']);
    stockSerials($this->laptop, ['SN-32GB'], $variant);
    serializedCartLine($this->cart, $this->laptop, 1, $variant);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/orders', storefrontCheckout($this->cart, $this->shipping))
        ->assertCreated();

    expect(ProductSerial::where('serial_number', 'SN-32GB')->value('status'))->toBe(ProductSerial::RESERVED)
        ->and(ProductSerial::where('serial_number', 'SN-BASE')->value('status'))->toBe(ProductSerial::AVAILABLE);
});

test('checkout is refused, with nothing saved, when too few units are in stock', function (): void {
    stockSerials($this->laptop, ['SN-001']);
    serializedCartLine($this->cart, $this->laptop, 2);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/orders', storefrontCheckout($this->cart, $this->shipping))
        ->assertUnprocessable()
        ->assertJsonPath('message', "Only 1 unit(s) of {$this->laptop->name} are available. Please reduce the quantity.");

    expect(Order::count())->toBe(0)
        ->and(ProductSerial::where('serial_number', 'SN-001')->value('status'))->toBe(ProductSerial::AVAILABLE)
        ->and($this->cart->refresh()->status)->not->toBe('converted');
});

test('serial numbers that are sent are still used as chosen', function (): void {
    stockSerials($this->laptop, ['SN-001', 'SN-002']);
    serializedCartLine($this->cart, $this->laptop, 1);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/orders', storefrontCheckout($this->cart, $this->shipping, ['serial_numbers' => ['SN-002']]))
        ->assertCreated();

    expect(ProductSerial::where('serial_number', 'SN-002')->value('status'))->toBe(ProductSerial::RESERVED)
        ->and(ProductSerial::where('serial_number', 'SN-001')->value('status'))->toBe(ProductSerial::AVAILABLE);
});

test('a cart without serialized products checks out without serials', function (): void {
    $mouse = Product::factory()->create(['price' => 20, 'is_serialized' => false]);
    serializedCartLine($this->cart, $mouse, 3);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/orders', storefrontCheckout($this->cart, $this->shipping))
        ->assertCreated();

    expect(ProductSerial::count())->toBe(0);
});
