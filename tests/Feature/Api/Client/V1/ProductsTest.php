<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
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
                        'stock_quantity',
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
                    'stock_quantity',
                    'category_id',
                    'brand_id',
                    'images',
                    'variants',
                ],
            ]);
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
