<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_brands_success(): void
    {
        Brand::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/brands');

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
                        'logo',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_list_brands_excludes_inactive(): void
    {
        Brand::factory()->create(['is_active' => true]);
        Brand::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/brands');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_brand_detail(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple',
            'description' => 'Premium electronics brand',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/brands/{$brand->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $brand->id)
            ->assertJsonPath('data.name', 'Apple')
            ->assertJsonPath('data.slug', 'apple')
            ->assertJsonPath('data.description', 'Premium electronics brand');
    }

    public function test_get_brand_by_slug(): void
    {
        $brand = Brand::factory()->create([
            'slug' => 'samsung',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/brands/{$brand->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $brand->id);
    }

    public function test_get_inactive_brand_returns_not_found(): void
    {
        $brand = Brand::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/brands/{$brand->id}");

        $response->assertNotFound();
    }

    public function test_get_nonexistent_brand_returns_not_found(): void
    {
        $response = $this->getJson('/api/v1/brands/9999');

        $response->assertNotFound();
    }
}
