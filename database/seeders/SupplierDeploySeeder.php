<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * The deploy-time counterpart of SupplierSeeder, run by the GitHub deploy
 * workflow on every release. It gives a brand-new environment a starter
 * supplier list and nothing else:
 *
 *  - it only writes while the suppliers table is completely empty, trashed
 *    rows included. After the first deploy, whatever admins add, edit or
 *    delete on the Suppliers page is never undone or topped up;
 *  - it never touches products or stock movements, so real catalog data and
 *    stock history are left exactly as they are;
 *  - no supplier is made the store default. The stock form pre-selects the
 *    default on every supplier delivery, so admins pick it themselves rather
 *    than have a starter row recorded on real receipts.
 *
 * SupplierSeeder (local/testing only) is the one that also links products by
 * brand and backfills the demo opening-stock receipts.
 */
class SupplierDeploySeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::withTrashed()->exists()) {
            return;
        }

        foreach (SupplierSeeder::SUPPLIERS as $definition) {
            $attributes = collect($definition)->except('brands')->all();
            $attributes['is_default'] = false;

            Supplier::query()->create($attributes);
        }
    }
}
