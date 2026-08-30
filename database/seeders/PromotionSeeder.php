<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Seed 10 discount codes for the admin "Promotions & Campaigns" screen.
     *
     * The screen reads `Coupon` through PromotionController — not the older
     * `Promotion` model, which is no longer wired to any admin route. Column
     * names here follow the coupons table: the amount is `value`, the window
     * closes at `expires_at`, and `type` is constrained by a CHECK to exactly
     * 'percentage' or 'fixed'.
     *
     * The set covers every status the list derives in `promotionFromApi`
     * (see `src/services/promotions.js` in the frontend): active, paused via
     * `is_active`, expired by `expires_at`, and expired by usage cap — plus
     * open-ended and uncapped rows, and one with no banner so the gradient
     * fallback has something to show.
     *
     * Idempotent: re-running updates the row for a code rather than stacking
     * duplicates or tripping the unique index.
     */
    public function run(): void
    {
        foreach ($this->promotions() as $promotion) {
            Coupon::updateOrCreate(
                ['code' => $promotion['code']],
                [
                    'name' => $promotion['name'],
                    'description' => $promotion['description'],
                    'banner_image' => $promotion['banner_image'],
                    'type' => $promotion['type'],
                    'value' => $promotion['value'],
                    'min_order_amount' => $promotion['min_order_amount'],
                    'starts_at' => $promotion['starts_at'],
                    'expires_at' => $promotion['expires_at'],
                    'usage_limit' => $promotion['usage_limit'],
                    'user_limit' => $promotion['user_limit'],
                    'used_count' => $promotion['used_count'],
                    'is_active' => $promotion['is_active'],
                ]
            );
        }
    }

    /**
     * Deterministic 1600x700 placeholders, seeded by name so a given campaign
     * keeps the same banner across re-runs.
     */
    private function banner(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/1600/700";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function promotions(): array
    {
        return [
            [
                // Evergreen: running, open-ended, uncapped.
                'name' => 'New Customer Welcome',
                'code' => 'WELCOME10',
                'description' => '10% off your first order, no minimum spend.',
                'banner_image' => $this->banner('bekie-welcome'),
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 0.00,
                'starts_at' => now()->subDays(60),
                'expires_at' => null,
                'usage_limit' => null,
                'user_limit' => 1,
                'used_count' => 342,
                'is_active' => true,
            ],
            [
                'name' => 'Save 25 Dollars This Month',
                'code' => 'SAVE25NOW',
                'description' => 'Flat 25 off orders over 250 while stocks last.',
                'banner_image' => $this->banner('bekie-save25'),
                'type' => 'fixed',
                'value' => 25.00,
                'min_order_amount' => 250.00,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(30),
                'usage_limit' => 500,
                'user_limit' => 2,
                'used_count' => 118,
                'is_active' => true,
            ],
            [
                'name' => 'Free Shipping Over 99',
                'code' => 'FREESHIP99',
                'description' => 'Covers standard nationwide delivery on orders over 99.',
                'banner_image' => $this->banner('bekie-freeship'),
                'type' => 'fixed',
                'value' => 9.99,
                'min_order_amount' => 99.00,
                'starts_at' => now()->subDays(45),
                'expires_at' => null,
                'usage_limit' => null,
                'user_limit' => null,
                'used_count' => 1204,
                'is_active' => true,
            ],
            [
                'name' => 'Gaming Gear 15% Off',
                'code' => 'GAMER15',
                'description' => '15% off keyboards, mice and headsets.',
                'banner_image' => $this->banner('bekie-gamer15'),
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 50.00,
                'starts_at' => now()->subDays(14),
                'expires_at' => now()->addDays(16),
                'usage_limit' => 200,
                'user_limit' => 1,
                'used_count' => 87,
                'is_active' => true,
            ],
            [
                // No banner at all: exercises the gradient fallback.
                'name' => 'New Year Build Sale',
                'code' => 'NEWYEAR20',
                'description' => '20% off custom PC builds for the new year.',
                'banner_image' => null,
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 500.00,
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(200),
                'usage_limit' => 2500,
                'user_limit' => 1,
                'used_count' => 5,
                'is_active' => true,
            ],
            [
                // Paused: switched off, so the list renders it as Paused
                // regardless of its window.
                'name' => 'Black Friday Doorbusters',
                'code' => 'BLACKFRIDAY30',
                'description' => '30% off GPUs, SSDs and monitors. Not live yet.',
                'banner_image' => $this->banner('bekie-blackfriday'),
                'type' => 'percentage',
                'value' => 30.00,
                'min_order_amount' => 0.00,
                'starts_at' => now()->addDays(60),
                'expires_at' => now()->addDays(67),
                'usage_limit' => 1000,
                'user_limit' => 1,
                'used_count' => 0,
                'is_active' => false,
            ],
            [
                // Paused, nearer-term campaign.
                'name' => 'Back to School Bundle',
                'code' => 'BACKTOSCHOOL',
                'description' => 'Flat 50 off laptop bundles for students.',
                'banner_image' => $this->banner('bekie-school'),
                'type' => 'fixed',
                'value' => 50.00,
                'min_order_amount' => 699.00,
                'starts_at' => now()->addDays(14),
                'expires_at' => now()->addDays(45),
                'usage_limit' => 300,
                'user_limit' => 1,
                'used_count' => 0,
                'is_active' => false,
            ],
            [
                // Expired by date, still switched on.
                'name' => 'Summer Clearance',
                'code' => 'SUMMER50',
                'description' => '50% off last-generation parts. Campaign has ended.',
                'banner_image' => $this->banner('bekie-clearance'),
                'type' => 'percentage',
                'value' => 50.00,
                'min_order_amount' => 0.00,
                'starts_at' => now()->subDays(60),
                'expires_at' => now()->subDays(20),
                'usage_limit' => null,
                'user_limit' => null,
                'used_count' => 640,
                'is_active' => true,
            ],
            [
                // Expired by date AND exhausted: both exclusions at once.
                'name' => '24 Hour Flash Sale',
                'code' => 'FLASH24',
                'description' => '20% off for one day only. Fully redeemed.',
                'banner_image' => $this->banner('bekie-flash24'),
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 100.00,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->subDays(3),
                'usage_limit' => 100,
                'user_limit' => 1,
                'used_count' => 100,
                'is_active' => true,
            ],
            [
                // Exhausted only: inside its window, but the cap is spent.
                'name' => 'VIP 100 Credit',
                'code' => 'VIPFIXED100',
                'description' => 'Flat 100 off for invited VIP customers.',
                'banner_image' => $this->banner('bekie-vip100'),
                'type' => 'fixed',
                'value' => 100.00,
                'min_order_amount' => 1000.00,
                'starts_at' => now()->subDays(90),
                'expires_at' => null,
                'usage_limit' => 50,
                'user_limit' => 1,
                'used_count' => 50,
                'is_active' => true,
            ],
        ];
    }
}
