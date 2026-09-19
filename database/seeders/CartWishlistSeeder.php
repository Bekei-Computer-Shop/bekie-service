<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CartWishlistSeeder extends Seeder
{
    private const USER_EMAIL = 'cart-wishlist@example.com';

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'first_name' => 'Cart',
                'last_name' => 'Wishlist User',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'is_active' => true,
                'is_banned' => false,
            ],
        );

        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        $product = Product::query()->where('is_active', true)->first();

        if (! $product) {
            $product = Product::factory()->create([
                'is_active' => true,
                'in_stock' => true,
                'stock_quantity' => 10,
            ]);
        }

        $cart = Cart::updateOrCreate(
            ['user_id' => $user->id, 'session_id' => null],
            [
                'currency' => 'USD',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'status' => 'active',
                'last_activity_at' => now(),
            ],
        );

        $unitPrice = $product->sale_price ?? $product->price;

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => null],
            [
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'sale_price' => $product->sale_price,
                'cost_price' => $product->cost_price,
                'subtotal' => $unitPrice,
                'discount' => 0,
                'total' => $unitPrice,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'is_available' => true,
            ],
        );

        $cart->update([
            'subtotal' => $unitPrice,
            'grand_total' => $unitPrice,
        ]);

        $wishlist = Wishlist::updateOrCreate(
            ['user_id' => $user->id],
            [
                'session_id' => null,
                'name' => 'My Wishlist',
                'description' => null,
                'is_public' => false,
                'is_active' => true,
            ],
        );

        WishlistItem::updateOrCreate(
            ['wishlist_id' => $wishlist->id, 'product_id' => $product->id, 'product_variant_id' => null],
            [
                'quantity' => 1,
                'metadata' => ['seeded_by' => self::class],
            ],
        );
    }
}
