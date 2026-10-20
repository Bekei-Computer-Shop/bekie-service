<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    /**
     * @var array<int, array<string, int|float|string|null|bool>>
     */
    private const METHODS = [
        [
            'name' => 'Store Pickup',
            'code' => 'store_pickup',
            'description' => 'Collect your order from our store after receiving a ready-for-pickup notification.',
            'base_price' => 0,
            'price_per_kg' => null,
            'min_weight' => null,
            'max_weight' => null,
            'min_delivery_days' => 1,
            'max_delivery_days' => 2,
            'is_active' => true,
            'type' => 'pickup',
            'sort_order' => 10,
        ],
        [
            'name' => 'Economy Delivery',
            'code' => 'economy_delivery',
            'description' => 'Tracked delivery for customers who prefer a lower-cost shipping option.',
            'base_price' => 4.99,
            'price_per_kg' => 0.75,
            'min_weight' => null,
            'max_weight' => 20,
            'min_delivery_days' => 5,
            'max_delivery_days' => 8,
            'is_active' => true,
            'type' => 'courier',
            'sort_order' => 20,
        ],
        [
            'name' => 'Standard Courier',
            'code' => 'standard_courier',
            'description' => 'Tracked door-to-door delivery for most orders.',
            'base_price' => 8.99,
            'price_per_kg' => 1.25,
            'min_weight' => null,
            'max_weight' => 30,
            'min_delivery_days' => 3,
            'max_delivery_days' => 5,
            'is_active' => true,
            'type' => 'courier',
            'sort_order' => 30,
        ],
        [
            'name' => 'Express Courier',
            'code' => 'express_courier',
            'description' => 'Priority tracked delivery for time-sensitive orders.',
            'base_price' => 19.99,
            'price_per_kg' => 2.5,
            'min_weight' => null,
            'max_weight' => 20,
            'min_delivery_days' => 1,
            'max_delivery_days' => 2,
            'is_active' => true,
            'type' => 'courier',
            'sort_order' => 40,
        ],
        [
            'name' => 'Same-Day Local Delivery',
            'code' => 'same_day_local',
            'description' => 'Same-day delivery for eligible addresses in the local service area.',
            'base_price' => 14.99,
            'price_per_kg' => 2,
            'min_weight' => null,
            'max_weight' => 15,
            'min_delivery_days' => 1,
            'max_delivery_days' => 1,
            'is_active' => true,
            'type' => 'courier',
            'sort_order' => 50,
        ],
    ];

    public function run(): void
    {
        foreach (self::METHODS as $attributes) {
            $shippingMethod = ShippingMethod::withTrashed()->updateOrCreate(
                ['code' => $attributes['code']],
                $attributes
            );

            if ($shippingMethod->trashed()) {
                $shippingMethod->restore();
            }
        }
    }
}
