<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('shipping_####'),
            'description' => fake()->sentence(),
            'base_price' => fake()->randomFloat(2, 0, 100),
            'price_per_kg' => fake()->randomFloat(2, 0, 10),
            'min_weight' => null,
            'max_weight' => null,
            'min_delivery_days' => 1,
            'max_delivery_days' => 5,
            'is_active' => true,
            'type' => 'courier',
            'sort_order' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
