<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSerial;
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

    $sold = app(ProductSerialService::class)->assignToOrder($serial, $customer->id, $order->id);

    expect($sold->status)->toBe(ProductSerial::SOLD)
        ->and($sold->customer_id)->toBe($customer->id)
        ->and($sold->warranty_start_at)->not->toBeNull()
        ->and($sold->warranty_end_at)->not->toBeNull();
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
