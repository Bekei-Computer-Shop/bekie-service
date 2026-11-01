<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderHistoryImageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $jti = Str::random(64);
        ApiToken::factory()->for($this->user)->create(['scope' => 'client', 'token' => hash('sha256', $jti)]);
        $this->jwtToken = (new JwtService)->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
            'exp' => now()->addHour()->timestamp,
        ]);
    }

    public function test_order_history_items_show_the_product_thumbnail(): void
    {
        $product = Product::factory()->create(['thumbnail' => 'https://cdn.example.com/laptop.jpg']);
        $this->orderWith($product);

        $this->withToken($this->jwtToken)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.image', 'https://cdn.example.com/laptop.jpg');
    }

    public function test_a_variant_picture_is_preferred_over_the_product_thumbnail(): void
    {
        $product = Product::factory()->create(['thumbnail' => 'https://cdn.example.com/laptop.jpg']);
        $variant = ProductVariant::factory()->for($product)->create(['image' => 'https://cdn.example.com/laptop-silver.jpg']);
        $order = $this->orderWith($product, $variant);

        $this->withToken($this->jwtToken)->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.image', 'https://cdn.example.com/laptop-silver.jpg');
    }

    public function test_a_variant_without_a_picture_falls_back_to_the_product_thumbnail(): void
    {
        $product = Product::factory()->create(['thumbnail' => 'https://cdn.example.com/laptop.jpg']);
        $variant = ProductVariant::factory()->for($product)->create(['image' => null]);
        $this->orderWith($product, $variant);

        $this->withToken($this->jwtToken)->getJson('/api/v1/orders')
            ->assertJsonPath('data.0.items.0.image', 'https://cdn.example.com/laptop.jpg');
    }

    public function test_a_product_removed_from_the_store_keeps_its_image_in_history(): void
    {
        $product = Product::factory()->create(['thumbnail' => 'https://cdn.example.com/discontinued.jpg']);
        $this->orderWith($product);
        $product->delete();

        $this->withToken($this->jwtToken)->getJson('/api/v1/orders')
            ->assertJsonPath('data.0.items.0.image', 'https://cdn.example.com/discontinued.jpg');
    }

    public function test_image_is_null_when_the_product_has_none(): void
    {
        $this->orderWith(Product::factory()->create(['thumbnail' => null]));

        $this->withToken($this->jwtToken)->getJson('/api/v1/orders')
            ->assertJsonPath('data.0.items.0.image', null);
    }

    public function test_images_are_loaded_without_a_query_per_item(): void
    {
        $this->orderWith(Product::factory()->create());
        $queries = $this->queriesFor('/api/v1/orders');

        foreach (range(1, 4) as $i) {
            $this->orderWith(Product::factory()->create());
        }

        $this->assertSame($queries, $this->queriesFor('/api/v1/orders'));
    }

    private function queriesFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($this->jwtToken)->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function orderWith(Product $product, ?ProductVariant $variant = null): Order
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => uniqid('ORD-', true),
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 100,
            'currency' => 'USD',
            'payment_method' => 'manual',
            'payment_status' => 'paid',
            'status' => 'processing',
            'shipping_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity_shipped' => 0,
            'quantity_refunded' => 0,
            'status' => 'pending',
        ]);

        return $order;
    }
}
