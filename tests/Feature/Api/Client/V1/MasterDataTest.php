<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_master_categories(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/master/categories');

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
                    ],
                ],
            ]);
    }

    public function test_get_master_brands(): void
    {
        Brand::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/master/brands');

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
                    ],
                ],
            ]);
    }

    public function test_get_master_shipping_methods(): void
    {
        ShippingMethod::factory()->count(2)->active()->create();

        $response = $this->getJson('/api/v1/master/shipping-methods');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                    ],
                ],
            ]);
    }

    public function test_master_data_excludes_inactive(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        Brand::factory()->create(['is_active' => true]);
        Brand::factory()->create(['is_active' => false]);

        ShippingMethod::factory()->active()->create();
        ShippingMethod::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/master/categories');
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/v1/master/brands');
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/v1/master/shipping-methods');
        $response->assertJsonCount(1, 'data');
    }
}
