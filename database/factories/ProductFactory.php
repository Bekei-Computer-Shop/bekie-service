<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'barcode' => fake()->unique()->ean13(),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 500),
            'sale_price' => null,
            'cost_price' => fake()->randomFloat(2, 0, 500),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'min_stock_alert' => 5,
            'track_inventory' => true,
            'in_stock' => true,
            'weight' => fake()->randomFloat(2, 0.1, 5),
            'length' => null,
            'width' => null,
            'height' => null,
            'thumbnail' => null,
            'meta_title' => null,
            'meta_description' => null,
            'is_active' => true,
            'is_featured' => false,
            'is_digital' => false,
            'views_count' => 0,
            'sales_count' => 0,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => [
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }
}
