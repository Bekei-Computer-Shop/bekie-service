<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ProductSerialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('serials are received with immutable history and duplicate protection', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $service = app(ProductSerialService::class);

    $created = $service->receive(['SN-001', 'SN-002'], (string) $product->id, null, 'Main', 'PO-1');

    expect($created)->toHaveCount(2)
        ->and(ProductSerial::where('serial_number', 'SN-001')->first()->status)->toBe(ProductSerial::AVAILABLE)
        ->and(ProductSerial::where('serial_number', 'SN-001')->first()->history)->toHaveCount(1);

    expect(fn () => $service->receive(['SN-001'], (string) $product->id))->toThrow(InvalidArgumentException::class);
});

test('serial sale assigns ownership and starts warranty', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $customer = User::factory()->create();
    $order = Order::create(['user_id' => $customer->id, 'order_number' => 'ORD-SERIAL-1']);
    $serial = app(ProductSerialService::class)->receive(['SN-SALE'], (string) $product->id)[0];
    app(ProductSerialService::class)->reserveForOrder(['SN-SALE'], $customer->id, $order->id);

    $sold = app(ProductSerialService::class)->assignToOrder($serial, $customer->id, $order->id);

    expect($sold->status)->toBe(ProductSerial::SOLD)
        ->and($sold->customer_id)->toBe($customer->id)
        ->and($sold->warranty_start_at)->not->toBeNull()
        ->and($sold->warranty_end_at)->not->toBeNull();
});

test('available serials cannot bypass reservation before sale', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $customer = User::factory()->create();
    $order = Order::create(['user_id' => $customer->id, 'order_number' => 'ORD-SERIAL-NO-BYPASS']);
    $serial = app(ProductSerialService::class)->receive(['SN-NO-BYPASS'], (string) $product->id)[0];

    expect(fn () => app(ProductSerialService::class)->assignToOrder($serial, $customer->id, $order->id))
        ->toThrow(InvalidArgumentException::class, 'must be reserved');
});

test('serial receiving rejects a variant from another product', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $otherProduct = Product::factory()->create(['is_serialized' => true]);
    $variant = ProductVariant::create([
        'product_id' => $otherProduct->id,
        'name' => 'Other variant',
        'slug' => 'other-variant',
        'sku' => 'OTHER-VARIANT',
        'price' => 10,
        'stock_quantity' => 0,
    ]);

    expect(fn () => app(ProductSerialService::class)->receive(['SN-MISMATCH'], (string) $product->id, $variant->id))
        ->toThrow(InvalidArgumentException::class, 'does not belong to the product');
});

test('serial lifecycle rejects invalid transitions', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $serial = app(ProductSerialService::class)->receive(['SN-TRANSITION'], (string) $product->id)[0];

    expect(fn () => app(ProductSerialService::class)->transition($serial, ProductSerial::SOLD))
        ->toThrow(InvalidArgumentException::class, 'Cannot change serial');
});

test('batch serial sale is atomic when one selected serial is unavailable', function (): void {
    $product = Product::factory()->create(['is_serialized' => true]);
    $customer = User::factory()->create();
    $order = Order::create(['user_id' => $customer->id, 'order_number' => 'ORD-SERIAL-ATOMIC']);
    $serials = app(ProductSerialService::class)->receive(['SN-ATOMIC-1', 'SN-ATOMIC-2'], (string) $product->id);
    $serials[1]->update(['status' => ProductSerial::SOLD]);

    expect(fn () => app(ProductSerialService::class)->sellForOrder(
        ['SN-ATOMIC-1', 'SN-ATOMIC-2'],
        $customer->id,
        $order->id,
    ))->toThrow(InvalidArgumentException::class, 'must be reserved for this order');

    expect($serials[0]->fresh()->status)->toBe(ProductSerial::AVAILABLE);
});

test('customers can only view their own serial products', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->create(['is_serialized' => true]);
    $serial = ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-OWNER', 'status' => ProductSerial::SOLD, 'customer_id' => $owner->id]);

    $ownerToken = app(AuthService::class)->createToken($owner, request())['access_token'];
    $this->withToken($ownerToken)->getJson('/api/v1/my-products')->assertOk()->assertJsonFragment(['serial_number' => 'SN-OWNER']);

    $otherToken = app(AuthService::class)->createToken($other, request())['access_token'];
    $this->withToken($otherToken)->getJson('/api/v1/my-products/'.$serial->id)->assertNotFound();
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
