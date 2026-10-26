<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_products_returns_recent_active_in_stock_products_newest_first(): void
    {
        $this->travelTo(now());

        $atCutoff = Product::factory()->create([
            'created_at' => now()->subDays(30),
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        $newest = Product::factory()->create([
            'created_at' => now()->subDay(),
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        Product::factory()->create([
            'created_at' => now()->subDays(30)->subSecond(),
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        Product::factory()->create([
            'created_at' => now()->subDay(),
            'is_active' => false,
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        Product::factory()->outOfStock()->create([
            'created_at' => now()->subDay(),
        ]);
        $deleted = Product::factory()->create([
            'created_at' => now()->subDay(),
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        $deleted->delete();

        $this->getJson('/api/v1/products/new')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $atCutoff->id);
    }

    public function test_new_products_returns_empty_data_when_no_products_match(): void
    {
        $this->getJson('/api/v1/products/new')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(0, 'data');
    }

    public function test_new_products_uses_default_and_requested_pagination_limits(): void
    {
        Product::factory()->count(25)->create([
            'created_at' => now()->subDay(),
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);

        $this->getJson('/api/v1/products/new')
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $this->getJson('/api/v1/products/new?limit=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_new_products_rejects_invalid_pagination(): void
    {
        $this->getJson('/api/v1/products/new?limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->getJson('/api/v1/products/new?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->getJson('/api/v1/products/new?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }
}
