<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $product = Product::factory();
        return [
            'cart_id' => Cart::factory(),
            'product_id' => $product,
            'product_variant_id' => null,
            'quantity' => 1,
            'unit_price' => 100.00,
            'sale_price' => null,
            'cost_price' => 50.00,
            'subtotal' => 100.00,
            'discount' => 0,
            'total' => 100.00,
            'product_name' => $this->faker->word(),
            'product_sku' => $this->faker->unique()->word(),
            'variant_name' => null,
            'variant_attributes' => null,
            'is_available' => true,
        ];
    }
}
