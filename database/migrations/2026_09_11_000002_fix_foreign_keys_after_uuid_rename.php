<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * After renaming products.uuid to products.id, update all foreign key
     * constraints to reference the new column name.
     */
    private const CHILDREN = [
        'product_variants' => 'cascade',
        'product_images' => 'cascade',
        'cart_items' => 'cascade',
        'order_items' => 'set null',
        'wishlist_items' => 'set null',
        'coupon_product' => 'cascade',
        'reviews' => 'cascade',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHILDREN as $table => $onDelete) {
            DB::statement("alter table {$table} drop constraint if exists {$table}_product_id_foreign;");

            // Delete orphaned rows that reference non-existent products
            DB::statement(
                "delete from {$table}
                 where product_id is not null
                   and not exists (select 1 from products where products.id = {$table}.product_id)"
            );

            DB::statement(
                "alter table {$table} add constraint {$table}_product_id_foreign
                 foreign key (product_id) references products (id) on delete {$onDelete};"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHILDREN as $table => $onDelete) {
            DB::statement("alter table {$table} drop constraint if exists {$table}_product_id_foreign;");
            DB::statement(
                "alter table {$table} add constraint {$table}_product_id_foreign
                 foreign key (product_id) references products (uuid) on delete {$onDelete};"
            );
        }
    }
};
