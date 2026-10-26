<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Database\Factories\ReviewFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_products_success(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'sku',
                        'price',
                        'sale_price',
                        'category_id',
                        'brand_id',
                    ],
                ],
            ]);
    }

    public function test_list_products_excludes_inactive(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_products_by_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products?category_id={$category->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_products_by_brand(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create([
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);
        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products?brand_id={$brand->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_products_pagination(): void
    {
        Product::factory()->count(25)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?page=2');

        $response->assertOk();
    }

    public function test_best_selling_products_use_paid_sales_and_approved_reviews(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'sales_count' => 0]);
        $pendingProduct = Product::factory()->create(['is_active' => true]);
        $unratedProduct = Product::factory()->create(['is_active' => true]);
        $this->recordPaidSale($product, 5);
        $this->recordPaidSale($pendingProduct, 5, 'pending');
        $this->recordPaidSale($unratedProduct, 10);
        ReviewFactory::new()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'status' => 'approved',
        ]);
        ReviewFactory::new()->create([
            'product_id' => $product->id,
            'rating' => 1,
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/products/best-selling')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.sales_count', 5)
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.reviews_count', 1);
    }

    public function test_on_promotion_products_require_a_lower_sale_price(): void
    {
        $promoted = Product::factory()->create([
            'is_active' => true,
            'price' => 100,
            'sale_price' => 80,
        ]);
        Product::factory()->create([
            'is_active' => true,
            'price' => 100,
            'sale_price' => 100,
        ]);

        $this->getJson('/api/v1/products/on-promotion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $promoted->id)
            ->assertJsonPath('data.0.price', 100)
            ->assertJsonPath('data.0.sale_price', 80);
    }

    private function recordPaidSale(Product $product, int $quantity, string $paymentStatus = 'paid'): void
    {
        $order = Order::create([
            'order_number' => uniqid('ORD-', true),
            'subtotal' => $quantity * 100,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => $quantity * 100,
            'currency' => 'USD',
            'payment_method' => 'manual',
            'payment_status' => $paymentStatus,
            'status' => 'processing',
            'shipping_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 100,
            'sale_price' => null,
            'cost_price' => null,
            'subtotal' => $quantity * 100,
            'discount' => 0,
            'tax' => 0,
            'total' => $quantity * 100,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity_shipped' => 0,
            'quantity_refunded' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_get_product_detail(): void
    {
        $product = Product::factory()->create([
            'name' => 'Laptop Pro',
            'slug' => 'laptop-pro',
            'sku' => 'LAPTOP-001',
            'price' => 1299.99,
            'sale_price' => 999.99,
            'short_description' => 'High performance laptop',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Laptop Pro')
            ->assertJsonPath('data.slug', 'laptop-pro')
            ->assertJsonPath('data.price', 1299.99)
            ->assertJsonPath('data.sale_price', 999.99)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'price',
                    'sale_price',
                    'short_description',
                    'description',
                    'category_id',
                    'brand_id',
                    'images',
                    'variants',
                ],
            ]);
    }

    public function test_public_product_responses_hide_internal_inventory_fields(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 25,
            'min_stock_alert' => 5,
            'track_inventory' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Inventory test variant',
            'slug' => 'inventory-test-variant',
            'sku' => 'INVENTORY-TEST-VARIANT',
            'price' => 10,
            'in_stock' => true,
            'stock_quantity' => 10,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $listResponse = $this->getJson('/api/v1/products');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.in_stock', 1)
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.min_stock_alert')
            ->assertJsonMissingPath('data.0.track_inventory');

        $detailResponse = $this->getJson("/api/v1/products/{$product->id}");
        $detailResponse->assertOk()
            ->assertJsonPath('data.in_stock', 1)
            ->assertJsonMissingPath('data.stock_quantity')
            ->assertJsonMissingPath('data.min_stock_alert')
            ->assertJsonMissingPath('data.track_inventory');

        $variantResponse = $this->getJson("/api/v1/products/{$product->id}/variants");
        $variantResponse->assertOk()
            ->assertJsonPath('data.0.in_stock', 1)
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.track_inventory');
    }

    public function test_get_product_by_uuid(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_get_product_includes_images(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductImage::create([
            'product_id' => $product->id,
            'image' => 'https://cdn.example.com/product.jpg',
            'type' => 'gallery',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.images.0.url', 'https://cdn.example.com/product.jpg')
            ->assertJsonPath('data.images.0.is_active', true);
    }

    public function test_get_product_excludes_cost_price(): void
    {
        $product = Product::factory()->create([
            'cost_price' => 500.00,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonMissingPath('data.cost_price');
    }

    public function test_get_inactive_product_returns_not_found(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertNotFound();
    }

    public function test_get_nonexistent_product_returns_not_found(): void
    {
        $response = $this->getJson('/api/v1/products/9999');

        $response->assertNotFound();
    }

    public function test_product_detail_increments_view_count(): void
    {
        $product = Product::factory()->create(['views_count' => 0, 'is_active' => true]);

        $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        expect($product->fresh()->views_count)->toBe(1);
    }
}
