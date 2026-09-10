<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
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

    $request = Request::create('/api/v1/wishlist', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $this->token = (new AuthService)->createToken($this->user, $request)['access_token'];
});

test('get empty wishlist returns empty items array', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wishlist');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'user_id' => $this->user->id,
                'name' => 'My Wishlist',
                'items' => [],
                'is_active' => true,
            ],
        ]);

    // Verify no wishlist was actually created
    expect(Wishlist::where('user_id', $this->user->id)->count())->toBe(0);
});

test('get wishlist with items returns items', function (): void {
    $product = Product::factory()->create();
    $wishlist = Wishlist::factory()->for($this->user)->create();
    WishlistItem::factory()->for($wishlist)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wishlist');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $wishlist->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                    ],
                ],
            ],
        ]);
});

test('seeded wishlist item is returned for the seeded user', function (): void {
    $this->seed(CartWishlistSeeder::class);

    $user = User::where('email', 'cart-wishlist@example.com')->firstOrFail();
    $request = Request::create('/api/v1/wishlist', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $token = (new AuthService)->createToken($user, $request)['access_token'];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/wishlist');

    $wishlistItem = WishlistItem::whereHas('wishlist', fn ($query) => $query->where('user_id', $user->id))->firstOrFail();

    $response->assertStatus(200)
        ->assertJsonPath('data.items.0.product_id', $wishlistItem->product_id);
});

test('unauthenticated request returns 401', function (): void {
    $response = $this->getJson('/api/v1/wishlist');

    $response->assertStatus(401);
});

test('add item to empty wishlist creates wishlist', function (): void {
    $product = Product::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/wishlist/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

    $response->assertStatus(201);

    // Verify wishlist was created
    $wishlist = Wishlist::where('user_id', $this->user->id)->first();
    expect($wishlist)->not->toBeNull();
});

test('add invalid product returns 422', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/wishlist/items', [
            'product_id' => 'invalid-uuid',
        ]);

    $response->assertStatus(422);
});

test('remove item from wishlist', function (): void {
    $product = Product::factory()->create();
    $wishlist = Wishlist::factory()->for($this->user)->create();
    $item = WishlistItem::factory()->for($wishlist)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson(
            "/api/v1/wishlist/items/{$item->id}"
        );

    $response->assertStatus(204);
    expect(WishlistItem::find($item->id))->toBeNull();
});

test('remove item from other users wishlist returns 404', function (): void {
    $otherUser = User::factory()->create();
    $product = Product::factory()->create();
    $wishlist = Wishlist::factory()->for($otherUser)->create();
    $item = WishlistItem::factory()->for($wishlist)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson(
            "/api/v1/wishlist/items/{$item->id}"
        );

    $response->assertStatus(404);
});

test('check product in empty wishlist returns false', function (): void {
    $product = Product::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wishlist/check?product_id=' . $product->id);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'exists' => false,
                'item' => null,
            ],
        ]);

    // Verify no wishlist was created
    expect(Wishlist::where('user_id', $this->user->id)->count())->toBe(0);
});

test('check product in wishlist returns true', function (): void {
    $product = Product::factory()->create();
    $wishlist = Wishlist::factory()->for($this->user)->create();
    $item = WishlistItem::factory()->for($wishlist)->create([
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wishlist/check?product_id=' . $product->id);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'exists' => true,
            ],
        ]);
});
