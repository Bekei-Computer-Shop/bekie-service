<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'logo' => null,
            'website' => fake()->url(),
            'description' => fake()->sentence(),
            'meta_title' => null,
            'meta_description' => null,
            'facebook' => null,
            'instagram' => null,
            'twitter' => null,
            'youtube' => null,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'products_count' => 0,
        ];
    }
}