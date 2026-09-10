<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('user');

    $request = Request::create('/api/v1/products', 'GET', server: ['HTTP_HOST' => 'localhost']);
    $this->token = (new AuthService)->createToken($this->user, $request)['access_token'];
});

test('get products returns paginated data', function (): void {
    Product::factory()->count(5)->create(['is_active' => true]);

    $response = $this->getJson('/api/v1/products');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
            'links',
            'meta',
        ]);
});

test('get categories returns paginated data', function (): void {
    Category::factory()->count(3)->create(['is_active' => true, 'parent_id' => null]);

    $response = $this->getJson('/api/v1/categories');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
            'links',
            'meta',
        ]);
});

test('get brands returns paginated data', function (): void {
    Brand::factory()->count(3)->create(['is_active' => true]);

    $response = $this->getJson('/api/v1/brands');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
            'links',
            'meta',
        ]);
});

test('get product by id returns product', function (): void {
    $product = Product::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
        ]);
});

test('get inactive product returns 404', function (): void {
    $product = Product::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertStatus(404);
});

test('get nonexistent product returns 404', function (): void {
    $response = $this->getJson('/api/v1/products/nonexistent-id');

    $response->assertStatus(404);
});

test('authenticated user empty cart returns empty items', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/cart');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'user_id' => $this->user->id,
                'items' => [],
                'subtotal' => 0,
            ],
        ]);

    // Ensure no cart was created
    expect(Cart::where('user_id', $this->user->id)->count())->toBe(0);
});

test('authenticated user empty wishlist returns empty items', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wishlist');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'user_id' => $this->user->id,
                'items' => [],
                'name' => 'My Wishlist',
            ],
        ]);

    // Ensure no wishlist was created
    expect(Wishlist::where('user_id', $this->user->id)->count())->toBe(0);
});

test('authenticated user empty orders returns paginated empty', function (): void {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/orders');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
            'links',
            'meta',
        ]);
});

test('unauthenticated cart request returns 401', function (): void {
    $response = $this->getJson('/api/v1/cart');

    $response->assertStatus(401);
});

test('unauthenticated wishlist request returns 401', function (): void {
    $response = $this->getJson('/api/v1/wishlist');

    $response->assertStatus(401);
});

test('unauthenticated orders request returns 401', function (): void {
    $response = $this->getJson('/api/v1/orders');

    $response->assertStatus(401);
});

test('health check endpoint works', function (): void {
    $response = $this->getJson('/health');

    $response->assertStatus(200);
});
