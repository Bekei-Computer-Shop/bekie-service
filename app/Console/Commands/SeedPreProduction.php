<?php

namespace App\Console\Commands;

use Database\Seeders\AdminPermissionsSeeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PreProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPreProduction extends Command
{
    protected $signature = 'seed:production {--fresh : Drop all tables and reseed}';

    protected $description = 'Seed the database with realistic pre-production data';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('🗑️  Dropping all tables...');
            $this->call('migrate:fresh', ['--seed' => false]);
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        $this->info('');
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║  Pre-Production Database Seeding       ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->info('');

        // Seed permissions and admin user
        $this->info('📋 Seeding permissions and admin user...');
        $this->call('db:seed', ['--class' => AdminPermissionsSeeder::class]);
        $this->call('db:seed', ['--class' => AdminUserSeeder::class]);

        // Seed pre-production data
        $this->info('🌱 Seeding pre-production data...');
        $this->call('db:seed', ['--class' => PreProductionSeeder::class]);

        $this->info('');
        $this->info('✅ Pre-production seeding completed!');
        $this->info('');
        $this->showSummary();

        return self::SUCCESS;
    }

    private function showSummary(): void
    {
        $this->info('📊 Database Summary:');
        $this->info('─────────────────────────────────────────');

        $counts = [
            'Users' => DB::table('users')->count(),
            'Categories' => DB::table('categories')->count(),
            'Products' => DB::table('products')->count(),
            'Product Variants' => DB::table('product_variants')->count(),
            'Orders' => DB::table('orders')->count(),
            'Order Items' => DB::table('order_items')->count(),
            'Cart Items' => DB::table('cart_items')->count(),
            'Wishlist Items' => DB::table('wishlist_items')->count(),
            'Coupons' => DB::table('coupons')->count(),
        ];

        foreach ($counts as $entity => $count) {
            $this->line(sprintf('  %-20s : %3d', $entity, $count));
        }

        $this->info('─────────────────────────────────────────');
        $this->info('');
        $this->info('🔐 Default Admin Account:');
        $this->info('  Email: admin@example.com');
        $this->info('  Password: password');
        $this->info('');
        $this->info('👤 Sample Customer Accounts:');
        $this->info('  Email: john.doe@example.com / Password: password');
        $this->info('  Email: jane.smith@example.com / Password: password');
        $this->info('  Email: michael.johnson@example.com / Password: password');
        $this->info('');
        $this->info('🎟️  Sample Coupons:');
        $this->info('  WELCOME10 - 10% off (min $50)');
        $this->info('  SAVE20 - 20% off (min $100)');
        $this->info('  FLAT15 - $15 off (min $75)');
        $this->info('  SUMMER30 - 30% off (min $150)');
        $this->info('');
    }
}
