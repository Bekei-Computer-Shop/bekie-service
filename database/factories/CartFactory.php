<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
            'currency' => 'USD',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
            'coupon_code' => null,
            'status' => 'active',
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => null,
            'expires_at' => null,
            'last_activity_at' => now(),
            'metadata' => [],
        ];
    }

    public function withTotals(float $subtotal = 100, float $tax = 10, float $shipping = 5): static
    {
        return $this->state(function () use ($subtotal, $tax, $shipping) {
            $discountTotal = 0;
            $grandTotal = $subtotal + $tax + $shipping - $discountTotal;

            return [
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'shipping_total' => $shipping,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
            ];
        });
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => 'abandoned',
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn () => [
            'status' => 'converted',
        ]);
    }
}
