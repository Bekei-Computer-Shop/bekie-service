<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Order matters: permissions must exist before any role-grant logic and
     * before the admin user is created (the seeder below uses Spatie's role
     * cache, which reads from the permissions table).
     *
     * The demo seeders refuse to run outside APP_ENV=local/testing, and
     * AdminUserSeeder needs SEED_ADMIN_PASSWORD outside local. Nothing here
     * truncates a table; `php artisan demo:reset` rebuilds the demo orders.
     */
    public const SEEDERS = [
        AdminPermissionsSeeder::class,
        AdminUserSeeder::class,
        CategorySeeder::class,
        ProductCatalogSeeder::class,
        // Demo data for the admin Stock Management screens' low/out-of-stock filters.
        StockAlertSeeder::class,
        // More products, real variant configurations, serials and stock movements.
        DemoCatalogSeeder::class,
        // Orders take their shipping cost from these.
        ShippingMethodSeeder::class,
        // Groups (and their shoppers) must exist before promotions target them.
        CustomerGroupSeeder::class,
        PromotionSeeder::class,
        // Runs before the other order seeders so it, not chance, fills the
        // promotions with tight usage limits.
        PromotionUsageOrderSeeder::class,
        CustomerSeeder::class,
        DemoOrderSeeder::class,
        // Keeps the admin dashboard's "Orders This Week" chart from
        // showing near-zero every day.
        ThisWeekOrderSeeder::class,
        CartWishlistSeeder::class,
        DemoCartSeeder::class,
        BannerSeeder::class,
        ReviewSeeder::class,
        // Needs completed demo orders.
        VerifiedReviewSeeder::class,
        ContentSeeder::class,
        NotificationSeeder::class,
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(self::SEEDERS);

        // Every order seeder records usage as it goes; this is the safety net
        // that keeps each demo promotion's counter equal to its usage rows.
        PromotionSeeder::syncUsageCounts();

        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => Hash::make('password'),
        ]);
    }
}
