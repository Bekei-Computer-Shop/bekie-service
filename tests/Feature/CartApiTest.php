<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Database\Seeders\CartWishlistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('user');

    $request = Request::create('/api/v1/cart', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $this->token = (new AuthService)->createToken($this->user, $request)['access_token'];
});

test('get empty cart returns empty items array', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/cart');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'user_id' => $this->user->id,
                'items' => [],
                'subtotal' => 0,
                'grand_total' => 0,
            ],
        ]);

    // Verify no cart was actually created
    expect(Cart::where('user_id', $this->user->id)->count())->toBe(0);
});

test('get cart with items returns items', function (): void {
    $product = Product::factory()->create();
    $cart = Cart::factory()->for($this->user)->create();
    CartItem::factory()->for($cart)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/cart');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $cart->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                    ],
                ],
            ],
        ]);
});

test('seeded cart item is returned for the seeded user', function (): void {
    $this->seed(CartWishlistSeeder::class);

    $user = User::where('email', 'cart-wishlist@example.com')->firstOrFail();
    $request = Request::create('/api/v1/cart', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $token = (new AuthService)->createToken($user, $request)['access_token'];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/cart');

    $cartItem = CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $user->id))->firstOrFail();

    $response->assertStatus(200)
        ->assertJsonPath('data.items.0.product_id', $cartItem->product_id);
});

test('unauthenticated request returns 401', function (): void {
    $response = $this->getJson('/api/v1/cart');

    $response->assertStatus(401);
});

test('add item to empty cart creates cart and item', function (): void {
    $product = Product::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);

    // Verify cart was created
    $cart = Cart::where('user_id', $this->user->id)->first();
    expect($cart)->not->toBeNull();

    // Verify item exists
    expect($cart->items()->count())->toBe(1);
});

test('add invalid product returns 422', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/items', [
            'product_id' => 'invalid-uuid',
            'quantity' => 2,
        ]);

    $response->assertStatus(422);
});

test('update cart item quantity', function (): void {
    $product = Product::factory()->create();
    $cart = Cart::factory()->for($this->user)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson(
            "/api/v1/cart/items/{$item->id}",
            ['quantity' => 5]
        );

    $response->assertStatus(200);
    expect($item->refresh()->quantity)->toBe(5);
});

test('remove item from cart', function (): void {
    $product = Product::factory()->create();
    $cart = Cart::factory()->for($this->user)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson(
            "/api/v1/cart/items/{$item->id}"
        );

    $response->assertStatus(204);
    expect(CartItem::find($item->id))->toBeNull();
});

test('remove item from other users cart returns 404', function (): void {
    $otherUser = User::factory()->create();
    $product = Product::factory()->create();
    $cart = Cart::factory()->for($otherUser)->create();
    $item = CartItem::factory()->for($cart)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson(
            "/api/v1/cart/items/{$item->id}"
        );

    $response->assertStatus(404);
});
