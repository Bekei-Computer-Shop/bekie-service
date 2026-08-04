<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'product_id' => Product::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'barcode' => null,
            'price' => fake()->randomFloat(2, 5, 200),
            'sale_price' => null,
            'cost_price' => null,
            'stock_quantity' => fake()->numberBetween(0, 50),
            'min_stock_alert' => 5,
            'track_inventory' => true,
            'in_stock' => true,
            'weight' => null,
            'length' => null,
            'width' => null,
            'height' => null,
            'image' => null,
            'attributes' => ['color' => 'red', 'size' => 'm'],
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}