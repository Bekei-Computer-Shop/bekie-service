<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;

/**
 * Carts and wishlists in every state the cart and checkout screens handle.
 * CartWishlistSeeder (one cart, used by the API tests) is left as it is.
 *
 * Carts: ready to check out, eligible for a product promotion, with a valid
 * code, with an expired code, holding a sold-out item, a guest cart, and
 * abandoned / expired / converted ones for the admin.
 *
 * Stored totals are the plain item sums; promotions are priced live by the
 * cart API (ProductPromotionService::priceCart), as for a real cart.
 *
 * Re-running is safe: carts match on their owner (or the fixed guest
 * session), items on cart and variant.
 *
 * Usage: php artisan db:seed --class=DemoCartSeeder
 */
class DemoCartSeeder extends Seeder
{
    /** Fixed so the guest cart is found again on re-runs. */
    public const GUEST_SESSION_ID = '6f1c1d6e-2d1e-4b7a-9a43-5e0c1b7d9a01';

    /**
     * owner email (null = guest), status, code, days since last activity,
     * items [variant SKU, quantity].
     */
    private const CARTS = [
        ['shopper.leakhena11@bekie.test', 'active', null, 0, [['LEN-LEGPRO5I-32GB-1TB', 1], ['LOG-G502XP-BLACK', 1]]],
        ['shopper.veasna14@bekie.test', 'active', null, 0, [['HP-OMEN25L-RTX-4070-16GB', 1], ['KEY-K8PRO-GATERON-BROWN-US-ANSI', 1]]],
        ['shopper.makara15@bekie.test', 'active', 'WELCOME10', 1, [['SAM-T7SHIELD-2TB-BLACK', 2], ['ANK-USBC240-6FT-BLACK', 3]]],
        ['shopper.phalla19@bekie.test', 'active', 'OLDCODE20', 0, [['DEL-PSERIES-27-INCH-P2725H', 2]]],
        // The processor is sold out, so the line is flagged unavailable.
        ['shopper.samnang20@bekie.test', 'active', null, 2, [['AMD-R7-9700X-STD', 1], ['MSI-B650-TOMA-STD', 1]]],
        [null, 'active', null, 0, [['AS-DUAL-4060-OC', 1]]],
        ['shopper.vuthy22@bekie.test', 'abandoned', null, 12, [['APL-MBA13-M3-16GB-512GB', 1]]],
        ['shopper.kimheng24@bekie.test', 'expired', null, 40, [['SAM-870EVO-1TB', 1]]],
        ['shopper.socheata25@bekie.test', 'converted', null, 3, [['LOG-Z407-STD', 1]]],
    ];

    /** owner email, variant SKUs. */
    private const WISHLISTS = [
        ['shopper.narin1@bekie.test', ['LG-27GR95QE-STD', 'GIG-4070TIS-GOC-STD', 'NOC-NHD15G2-STD']],
        ['shopper.pisey5@bekie.test', ['APL-MBA13-M3-8GB-256GB', 'LOG-MXA3S-ROSE']],
        ['shopper.seyha8@bekie.test', ['MSI-KATANA15-RTX-4060-16GB', 'SS-NOVA7-PC-PLAYSTATION-BLACK', 'COR-K70PRO-CHERRY-MX-RED-US-ANSI', 'AOC-24G2SP-STD']],
        ['shopper.sitha18@bekie.test', ['DEL-OPTI7020M-CORE-I5-14500T-16GB-256GB', 'BRO-HLL2460DW-STD']],
        ['shopper.rachana13@bekie.test', ['AMD-R7-9700X-STD', 'LOG-G915TKL-GL-LINEAR']],
    ];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        foreach (self::CARTS as [$email, $status, $code, $idleDays, $items]) {
            $this->seedCart($email ? $this->user($email) : null, $status, $code, $idleDays, $items);
        }

        foreach (self::WISHLISTS as [$email, $skus]) {
            $this->seedWishlist($this->user($email), $skus);
        }

        $this->command?->info(sprintf('Seeded %d demo carts and %d wishlists.', count(self::CARTS), count(self::WISHLISTS)));
    }

    /** @param list<array{0: string, 1: int}> $items */
    private function seedCart(?User $user, string $status, ?string $code, int $idleDays, array $items): void
    {
        $lastActivity = now()->subDays($idleDays);
        $cart = $user
            ? Cart::withTrashed()->firstOrNew(['user_id' => $user->id, 'session_id' => null])
            : Cart::withTrashed()->firstOrNew(['session_id' => self::GUEST_SESSION_ID]);

        $cart->fill([
            'currency' => 'USD',
            'coupon_code' => $code,
            'status' => $status,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Bekie demo seeder',
            'last_activity_at' => $lastActivity,
            'expires_at' => $status === 'expired' ? now()->subDays(10) : now()->addDays(30),
            'metadata' => ['seeded_by' => self::class],
        ])->save();
        if ($cart->trashed()) {
            $cart->restore();
        }

        $subtotal = 0.0;
        foreach ($items as [$sku, $quantity]) {
            $variant = $this->variant($sku);
            $unit = (float) ($variant->sale_price ?? $variant->price);
            $lineTotal = round($unit * $quantity, 2);
            $subtotal += $lineTotal;

            CartItem::query()->updateOrCreate(
                ['cart_id' => $cart->id, 'product_variant_id' => $variant->id],
                [
                    'product_id' => $variant->product_id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $variant->price,
                    'sale_price' => $variant->sale_price,
                    'cost_price' => $variant->cost_price,
                    'subtotal' => $lineTotal,
                    'discount' => 0,
                    'total' => $lineTotal,
                    'product_name' => $variant->product->name,
                    'product_sku' => $variant->sku,
                    'variant_name' => $variant->name,
                    'variant_attributes' => $variant->attributes,
                    'is_available' => $variant->in_stock && $variant->product->is_active,
                ],
            );
        }

        $cart->update(['subtotal' => round($subtotal, 2), 'grand_total' => round($subtotal, 2)]);
    }

    /** @param list<string> $skus */
    private function seedWishlist(User $user, array $skus): void
    {
        $wishlist = Wishlist::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['name' => 'My Wishlist', 'is_public' => false, 'is_active' => true],
        );

        foreach ($skus as $sku) {
            $variant = $this->variant($sku);

            WishlistItem::query()->updateOrCreate(
                ['wishlist_id' => $wishlist->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id],
                ['quantity' => 1, 'metadata' => ['seeded_by' => self::class]],
            );
        }
    }

    private function user(string $email): User
    {
        return User::withTrashed()->where('email', $email)->first()
            ?? throw new \RuntimeException("Demo shopper {$email} is missing — run CustomerGroupSeeder first.");
    }

    /**
     * Found across soft-deleted products too, so a wishlist can still point
     * at the discontinued keyboard.
     */
    private function variant(string $sku): ProductVariant
    {
        return ProductVariant::query()
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->where('sku', $sku)
            ->first()
            ?? throw new \RuntimeException("Demo variant {$sku} is missing — run DemoCatalogSeeder first.");
    }
}
