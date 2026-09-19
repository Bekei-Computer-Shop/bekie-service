<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'order_id' => null,
            'rating' => $this->faker->numberBetween(1, 5),
            'title' => $this->faker->sentence(6),
            'comment' => $this->faker->paragraph(3),
            'status' => 'pending',
            'is_verified_purchase' => false,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
            'images' => null,
            'is_featured' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function verifiedPurchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => true,
            'order_id' => Order::factory(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'status' => 'approved',
        ]);
    }

    public function withImages(): static
    {
        return $this->state(fn (array $attributes) => [
            'images' => [
                'https://example.com/review1.jpg',
                'https://example.com/review2.jpg',
            ],
        ]);
    }
}
