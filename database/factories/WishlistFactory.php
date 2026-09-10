<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'is_public' => false,
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'session_id' => fake()->sha256(),
        ]);
    }
}
