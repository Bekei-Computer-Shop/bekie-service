<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_product_variants_success(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->count(3)->for($product)->active()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}/variants");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'slug',
                        'price',
                        'sale_price',
                        'stock_quantity',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_get_product_variants_excludes_inactive(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->active()->create();
        ProductVariant::factory()->for($product)->inactive()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}/variants");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_variants_returns_not_found_for_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}/variants");

        $response->assertNotFound();
    }

    public function test_get_variants_for_nonexistent_product(): void
    {
        $response = $this->getJson('/api/v1/products/9999/variants');

        $response->assertNotFound();
    }
}
