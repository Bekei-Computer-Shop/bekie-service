<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_categories_success(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_list_categories_excludes_inactive(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_category_detail(): void
    {
        $category = Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and accessories',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonPath('data.slug', 'electronics')
            ->assertJsonPath('data.description', 'Electronic devices and accessories');
    }

    public function test_get_category_by_slug(): void
    {
        $category = Category::factory()->create([
            'slug' => 'laptops',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_get_inactive_category_returns_not_found(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertNotFound();
    }

    public function test_get_nonexistent_category_returns_not_found(): void
    {
        $response = $this->getJson('/api/v1/categories/9999');

        $response->assertNotFound();
    }
}
