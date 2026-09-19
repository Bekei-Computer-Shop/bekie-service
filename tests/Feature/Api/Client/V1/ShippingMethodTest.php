<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shipping_methods_success(): void
    {
        ShippingMethod::factory()->count(3)->active()->create();

        $response = $this->getJson('/api/v1/shipping-methods');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'type',
                        'base_cost',
                        'cost_per_weight',
                        'is_active',
                        'sort_order',
                    ],
                ],
            ]);
    }

    public function test_list_shipping_methods_excludes_inactive(): void
    {
        ShippingMethod::factory()->active()->create();
        ShippingMethod::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/shipping-methods');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_shipping_methods_ordered_by_sort_order(): void
    {
        $method1 = ShippingMethod::factory()->active()->create(['sort_order' => 2]);
        $method2 = ShippingMethod::factory()->active()->create(['sort_order' => 1]);
        $method3 = ShippingMethod::factory()->active()->create(['sort_order' => 3]);

        $response = $this->getJson('/api/v1/shipping-methods');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $method2->id)
            ->assertJsonPath('data.1.id', $method1->id)
            ->assertJsonPath('data.2.id', $method3->id);
    }

    public function test_master_data_shipping_methods(): void
    {
        ShippingMethod::factory()->count(2)->active()->create();

        $response = $this->getJson('/api/v1/master/shipping-methods');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');
    }
}
