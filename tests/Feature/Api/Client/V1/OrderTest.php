<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApiToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = ApiToken::factory()->for($this->user)->create(['scope' => 'client']);
    }

    public function test_get_order_history_success(): void
    {
        Order::factory()->count(3)->for($this->user)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.user_id', $this->user->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'user_id',
                        'subtotal',
                        'discount_total',
                        'tax_total',
                        'shipping_total',
                        'grand_total',
                        'currency',
                        'payment_method',
                        'payment_status',
                        'status',
                        'shipping_status',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_get_order_history_pagination(): void
    {
        Order::factory()->count(20)->for($this->user)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('pagination.total', 20)
            ->assertJsonPath('pagination.per_page', 15);
    }

    public function test_get_order_history_only_shows_user_orders(): void
    {
        $otherUser = User::factory()->create();
        Order::factory()->for($this->user)->create();
        Order::factory()->for($otherUser)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_order_history_ordered_by_most_recent(): void
    {
        $oldOrder = Order::factory()->for($this->user)->create();
        $newOrder = Order::factory()->for($this->user)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newOrder->id)
            ->assertJsonPath('data.1.id', $oldOrder->id);
    }

    public function test_get_order_detail_success(): void
    {
        $order = Order::factory()->for($this->user)->create([
            'order_number' => 'ORD-12345',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', 'ORD-12345');
    }

    public function test_get_order_detail_forbidden_for_other_user(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->for($otherUser)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertForbidden();
    }

    public function test_get_order_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertUnauthorized();
    }

    public function test_get_order_detail_requires_authentication(): void
    {
        $order = Order::factory()->for($this->user)->create();

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertUnauthorized();
    }

    public function test_create_order_from_cart(): void
    {
        $shippingMethod = ShippingMethod::factory()->active()->create();
        $product = Product::factory()->create([
            'price' => 100,
            'track_inventory' => false,
        ]);

        $cart = Cart::factory()->for($this->user)->create();
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
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

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/orders', [
                'cart_id' => $cart->id,
                'shipping_method_id' => $shippingMethod->id,
                'email' => 'customer@example.com',
                'phone' => '+12345678901',
                'recipient_name' => 'John Doe',
                'address_line_1' => '123 Main St',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'United States',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.shipping_status', 'pending');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_create_order_requires_cart_items(): void
    {
        $shippingMethod = ShippingMethod::factory()->active()->create();
        $cart = Cart::factory()->for($this->user)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/orders', [
                'cart_id' => $cart->id,
                'shipping_method_id' => $shippingMethod->id,
                'email' => 'customer@example.com',
                'phone' => '+12345678901',
                'recipient_name' => 'John Doe',
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'postal_code' => '10001',
                'country' => 'United States',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cart must contain at least one item.');
    }

    public function test_create_order_forbidden_for_other_user_cart(): void
    {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->for($otherUser)->create();
        $shippingMethod = ShippingMethod::factory()->active()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token->token}")
            ->postJson('/api/v1/orders', [
                'cart_id' => $otherCart->id,
                'shipping_method_id' => $shippingMethod->id,
                'email' => 'customer@example.com',
                'phone' => '+12345678901',
                'recipient_name' => 'John Doe',
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'postal_code' => '10001',
                'country' => 'United States',
            ]);

        $response->assertForbidden();
    }

    public function test_create_order_requires_authentication(): void
    {
        $shippingMethod = ShippingMethod::factory()->active()->create();

        $response = $this->postJson('/api/v1/orders', [
            'cart_id' => 1,
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertUnauthorized();
    }
}
