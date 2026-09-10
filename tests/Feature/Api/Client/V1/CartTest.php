<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApiToken $token;

    private Cart $cart;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $jti = Str::random(64);
        $this->token = ApiToken::factory()->for($this->user)->create([
            'scope' => 'client',
            'token' => hash('sha256', $jti),
        ]);

        $jwtService = new JwtService;
        $this->jwtToken = $jwtService->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 24 * 60 * 60);

        $this->cart = Cart::factory()->for($this->user)->create();
    }

    public function test_get_cart_success(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        CartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'sale_price' => 100,
            'cost_price' => 50,
            'subtotal' => 200,
            'discount' => 0,
            'total' => 200,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/carts');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $this->cart->id)
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.total', 200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_create_or_update_cart(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->putJson('/api/v1/carts', [
                'currency' => 'USD',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_add_item_to_cart(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/carts/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.product_id', $product->id);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_requires_valid_product(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/carts/items', [
                'product_id' => 9999,
                'quantity' => 1,
            ]);

        $response->assertUnprocessable();
    }

    public function test_add_item_requires_quantity(): void
    {
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/carts/items', [
                'product_id' => $product->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_update_cart_item(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'sale_price' => 100,
            'cost_price' => 50,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->patchJson("/api/v1/carts/items/{$item->id}", [
                'quantity' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_update_cart_item_invalid_quantity(): void
    {
        $product = Product::factory()->create();
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'sale_price' => 100,
            'cost_price' => 50,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->patchJson("/api/v1/carts/items/{$item->id}", [
                'quantity' => 0,
            ]);

        $response->assertUnprocessable();
    }

    public function test_remove_item_from_cart(): void
    {
        $product = Product::factory()->create();
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'sale_price' => 100,
            'cost_price' => 50,
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->deleteJson("/api/v1/carts/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $item->id,
        ]);
    }

    public function test_cart_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/carts');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/v1/carts');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/v1/carts/items', []);
        $response->assertUnauthorized();
    }

    public function test_remove_nonexistent_item(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->deleteJson('/api/v1/carts/items/9999');

        $response->assertNotFound();
    }
}
