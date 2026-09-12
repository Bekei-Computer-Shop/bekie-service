<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PreProductionSeeder extends Seeder
{
    private array $categories = [];

    private array $products = [];

    private array $users = [];

    public function run(): void
    {
        $this->command->info('🌱 Starting pre-production seeding...');

        $this->seedCategories();
        $this->seedProducts();
        $this->seedUsers();
        $this->seedOrders();
        $this->seedCarts();
        $this->seedWishlists();
        $this->seedCoupons();

        $this->command->info('✅ Pre-production seeding completed!');
    }

    private function seedCategories(): void
    {
        $this->command->info('📁 Seeding categories...');

        $categoryData = [
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Computers & Laptops', 'slug' => 'computers-laptops'],
            ['name' => 'Smartphones', 'slug' => 'smartphones'],
            ['name' => 'Tablets', 'slug' => 'tablets'],
            ['name' => 'Audio', 'slug' => 'audio'],
            ['name' => 'Fashion', 'slug' => 'fashion'],
            ['name' => 'Men\'s Clothing', 'slug' => 'mens-clothing'],
            ['name' => 'Women\'s Clothing', 'slug' => 'womens-clothing'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden'],
            ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors'],
        ];

        foreach ($categoryData as $data) {
            $category = Category::firstOrCreate(['slug' => $data['slug']], $data);
            $this->categories[] = $category;
        }
    }

    /**
     * Delegates to ProductCatalogSeeder for the full 50-product catalog
     * (thumbnails, gallery images, 3+ variants per product, every field
     * populated). $this->products then feeds seedOrders/seedCarts/
     * seedWishlists below.
     */
    private function seedProducts(): void
    {
        $this->command->info('🛍️  Seeding products...');

        $this->call(ProductCatalogSeeder::class);

        $this->products = Product::query()->get()->all();
    }

    private function seedUsers(): void
    {
        $this->command->info('👥 Seeding users...');

        $userData = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
                'email' => 'john.doe@example.com',
                'phone' => '+1-555-0101',
                'is_active' => true,
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'username' => 'janesmith',
                'email' => 'jane.smith@example.com',
                'phone' => '+1-555-0102',
                'is_active' => true,
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'username' => 'mjohnson',
                'email' => 'michael.johnson@example.com',
                'phone' => '+1-555-0103',
                'is_active' => true,
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Williams',
                'username' => 'swilliams',
                'email' => 'sarah.williams@example.com',
                'phone' => '+1-555-0104',
                'is_active' => true,
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Brown',
                'username' => 'rbrown',
                'email' => 'robert.brown@example.com',
                'phone' => '+1-555-0105',
                'is_active' => true,
            ],
        ];

        foreach ($userData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    ...$data,
                    'password' => bcrypt('password123'),
                ]
            );
            $this->users[] = $user;
        }
    }

    private function seedOrders(): void
    {
        if (Order::query()->exists()) {
            $this->command->info('📦 Orders already seeded, skipping.');

            return;
        }

        $this->command->info('📦 Seeding orders...');

        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        for ($i = 0; $i < 12; $i++) {
            $user = $this->users[array_rand($this->users)];
            $defaultAddress = $user->addresses()->first() ?? $user->addresses()->create([
                'label' => 'Home',
                'full_name' => $user->name,
                'phone' => $user->phone,
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'United States',
                'is_default' => true,
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $defaultAddress->id,
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'customer_snapshot' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'address_snapshot' => [
                    'label' => $defaultAddress->label,
                    'full_name' => $defaultAddress->full_name,
                    'phone' => $defaultAddress->phone,
                    'address_line_1' => $defaultAddress->address_line_1,
                    'address_line_2' => $defaultAddress->address_line_2,
                    'city' => $defaultAddress->city,
                    'state' => $defaultAddress->state,
                    'postal_code' => $defaultAddress->postal_code,
                    'country' => $defaultAddress->country,
                ],
                'status' => $statuses[array_rand($statuses)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'payment_method' => ['credit_card', 'paypal', 'bank_transfer'][array_rand(['credit_card', 'paypal', 'bank_transfer'])],
                'currency' => 'USD',
                'subtotal' => 0,
                'discount_total' => rand(0, 50),
                'tax_total' => 0,
                'shipping_total' => 9.99,
                'grand_total' => 0,
                'notes' => 'Please deliver to reception.',
            ]);

            // Add order items
            $itemCount = rand(1, 3);
            $subtotal = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $this->products[array_rand($this->products)];
                $quantity = rand(1, 3);
                $unitPrice = $product->price;
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineTotal,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $lineTotal,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                ]);
            }

            $grandTotal = round($subtotal - $order->discount_total + $order->tax_total + $order->shipping_total, 2);
            $order->update([
                'subtotal' => $subtotal,
                'tax_total' => round($subtotal * 0.08, 2),
                'grand_total' => $grandTotal,
            ]);
        }
    }

    private function seedCarts(): void
    {
        $this->command->info('🛒 Seeding shopping carts...');

        foreach ($this->users as $user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            $itemCount = rand(2, 5);
            for ($i = 0; $i < $itemCount; $i++) {
                $product = $this->products[array_rand($this->products)];
                CartItem::updateOrCreate(
                    ['cart_id' => $cart->id, 'product_id' => $product->id],
                    [
                        'quantity' => rand(1, 3),
                        'unit_price' => $product->price,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                    ]
                );
            }
        }
    }

    private function seedWishlists(): void
    {
        $this->command->info('❤️  Seeding wishlists...');

        foreach ($this->users as $user) {
            $wishlist = Wishlist::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->first_name.'\'s Wishlist',
                    'is_public' => rand(0, 1),
                ]
            );

            if (! $wishlist->wasRecentlyCreated) {
                continue;
            }

            $itemCount = rand(3, 7);
            $selectedProducts = array_rand($this->products, min($itemCount, count($this->products)));

            foreach ((array) $selectedProducts as $productIndex) {
                WishlistItem::create([
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $this->products[$productIndex]->id,
                ]);
            }
        }
    }

    private function seedCoupons(): void
    {
        $this->command->info('🎟️  Seeding coupons...');

        $coupons = [
            [
                'name' => 'Welcome Discount',
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 50.00,
                'usage_limit' => 100,
                'used_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'name' => 'Save Big',
                'code' => 'SAVE20',
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 100.00,
                'usage_limit' => 50,
                'used_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(1),
                'is_active' => true,
            ],
            [
                'name' => 'Flat Discount',
                'code' => 'FLAT15',
                'type' => 'fixed',
                'value' => 15.00,
                'min_order_amount' => 75.00,
                'usage_limit' => 200,
                'used_count' => 0,
                'starts_at' => now()->subDays(7),
                'expires_at' => now()->addMonths(2),
                'is_active' => true,
            ],
            [
                'name' => 'Summer Sale',
                'code' => 'SUMMER30',
                'type' => 'percentage',
                'value' => 30.00,
                'min_order_amount' => 150.00,
                'usage_limit' => 25,
                'used_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $couponData) {
            Coupon::firstOrCreate(['code' => $couponData['code']], $couponData);
        }
    }
}
