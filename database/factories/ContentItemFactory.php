<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['news', 'page']),
            'title' => fake()->unique()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'category' => null,
            'image_url' => null,
            'status' => 'draft',
            'author_id' => User::factory(),
            'published_at' => null,
        ];
    }

    public function news(): static
    {
        return $this->state(fn (): array => [
            'type' => 'news',
            'category' => fake()->randomElement(['Product News', 'Guides', 'Promotions']),
            'image_url' => fake()->imageUrl(1200, 630),
        ]);
    }

    public function page(): static
    {
        return $this->state(fn (): array => [
            'type' => 'page',
            'category' => null,
            'image_url' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }
}
