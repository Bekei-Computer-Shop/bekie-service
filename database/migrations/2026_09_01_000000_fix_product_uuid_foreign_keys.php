<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every PostgreSQL child table still referencing products by the integer id.
     */
    private const CHILDREN = [
        'product_variants' => ['nullable' => false, 'on_delete' => 'cascade', 'single_index' => true],
        'product_images' => ['nullable' => false, 'on_delete' => 'cascade', 'single_index' => true],
        'cart_items' => ['nullable' => false, 'on_delete' => 'cascade', 'single_index' => true],
        'order_items' => ['nullable' => true, 'on_delete' => 'set_null', 'single_index' => true],
        'wishlist_items' => ['nullable' => true, 'on_delete' => 'set_null', 'single_index' => false],
        'coupon_product' => ['nullable' => false, 'on_delete' => 'cascade', 'single_index' => true],
        'reviews' => ['nullable' => false, 'on_delete' => 'cascade', 'single_index' => false],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $productsHasId = DB::selectOne(
            "select exists (
                select 1 from information_schema.columns
                where table_schema = current_schema()
                  and table_name = 'products'
                  and column_name = 'id'
            ) as exists;"
        );

        foreach (self::CHILDREN as $table => $cfg) {
            $hasTypeUuid = DB::selectOne(
                "select exists (
                    select 1 from information_schema.columns
                    where table_schema = current_schema()
                      and table_name = ?
                      and column_name = 'product_id'
                      and data_type = 'uuid'
                ) as exists;",
                [$table]
            );

            if ($hasTypeUuid && $hasTypeUuid->exists) {
                continue;
            }

            DB::statement("alter table {$table} add column if not exists product_uuid uuid;");
            DB::statement(
                "update {$table} child set product_uuid = products.uuid
                 from products
                 where child.product_id is not null
                   and products.id = child.product_id"
            );

            if (! $cfg['nullable']) {
                DB::statement(
                    "delete from {$table} child
                     where child.product_id is not null
                       and not exists (select 1 from products where products.id = child.product_id)"
                );
            }

            DB::statement("alter table {$table} drop constraint if exists {$table}_product_id_foreign;");
            if ($cfg['single_index']) {
                DB::statement("drop index if exists {$table}_product_id_index;");
            }
            if ($table === 'coupon_product') {
                DB::statement('alter table coupon_product drop constraint if exists coupon_product_pkey;');
            }
            if ($table === 'wishlist_items') {
                DB::statement('drop index if exists wishlist_items_wishlist_id_product_id_index;');
            }
            if ($table === 'reviews') {
                DB::statement('drop index if exists reviews_product_id_status_index;');
                DB::statement('drop index if exists reviews_user_id_product_id_index;');
            }

            DB::statement("alter table {$table} drop column if exists product_id;");
            DB::statement("alter table {$table} rename column product_uuid to product_id;");
            DB::statement("alter table {$table} alter column product_id type uuid using product_id::text::uuid;");

            if (! $cfg['nullable']) {
                DB::statement("alter table {$table} alter column product_id set not null;");
            }

            if ($cfg['single_index']) {
                DB::statement("create index {$table}_product_id_index on {$table} (product_id);");
            }
            if ($table === 'wishlist_items') {
                DB::statement('create index wishlist_items_wishlist_id_product_id_index on wishlist_items (wishlist_id, product_id);');
            }
            if ($table === 'coupon_product') {
                DB::statement('alter table coupon_product add primary key (coupon_id, product_id);');
            }
            if ($table === 'reviews') {
                DB::statement('create index reviews_product_id_status_index on reviews (product_id, status);');
                DB::statement('create index reviews_user_id_product_id_index on reviews (user_id, product_id);');
            }

            $onDelete = $cfg['on_delete'] === 'cascade' ? 'cascade' : 'set null';
            DB::statement(
                "alter table {$table} add constraint {$table}_product_id_foreign
                 foreign key (product_id) references products (uuid) on delete {$onDelete};"
            );
        }

        if ($productsHasId && $productsHasId->exists) {
            DB::statement('alter table products drop constraint if exists products_pkey;');
            DB::statement('alter table products alter column uuid set not null;');
            DB::statement('alter table products add primary key (uuid);');
            DB::statement('alter table products drop column if exists id;');
            DB::statement('drop sequence if exists products_id_seq;');

            $hasUuidUnique = DB::selectOne(
                "select exists (
                    select 1 from pg_constraint where conrelid = 'products'::regclass and conname = 'products_uuid_unique'
                ) as exists;"
            );
            if ($hasUuidUnique && $hasUuidUnique->exists) {
                DB::statement('alter table products drop constraint if exists products_uuid_unique cascade;');
            }
            DB::statement('drop index if exists products_uuid_unique;');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $productsHasUuid = DB::selectOne(
            "select exists (
                select 1 from information_schema.columns
                where table_schema = current_schema()
                  and table_name = 'products'
                  and column_name = 'uuid'
            ) as exists;"
        );

        foreach (self::CHILDREN as $table => $cfg) {
            $hasTypeUuid = DB::selectOne(
                "select exists (
                    select 1 from information_schema.columns
                    where table_schema = current_schema()
                      and table_name = ?
                      and column_name = 'product_id'
                      and data_type = 'uuid'
                ) as exists;",
                [$table]
            );

            if (! $hasTypeUuid || ! $hasTypeUuid->exists) {
                continue;
            }

            DB::statement("alter table {$table} drop constraint if exists {$table}_product_id_foreign;");
            if ($cfg['single_index']) {
                DB::statement("drop index if exists {$table}_product_id_index;");
            }
            if ($table === 'coupon_product') {
                DB::statement('alter table coupon_product drop constraint if exists coupon_product_pkey;');
            }
            if ($table === 'wishlist_items') {
                DB::statement('drop index if exists wishlist_items_wishlist_id_product_id_index;');
            }
            if ($table === 'reviews') {
                DB::statement('drop index if exists reviews_product_id_status_index;');
                DB::statement('drop index if exists reviews_user_id_product_id_index;');
            }

            DB::statement("alter table {$table} add column if not exists product_id_new bigint;");
            DB::statement(
                "update {$table} child set product_id_new = products.id
                 from products
                 where products.uuid = child.product_id"
            );

            DB::statement("alter table {$table} drop column if exists product_id;");
            DB::statement("alter table {$table} rename column product_id_new to product_id;");

            $onDelete = $cfg['on_delete'] === 'cascade' ? 'cascade' : 'set null';
            if ($cfg['single_index']) {
                DB::statement("create index {$table}_product_id_index on {$table} (product_id);");
            }
            if ($table === 'wishlist_items') {
                DB::statement('create index wishlist_items_wishlist_id_product_id_index on wishlist_items (wishlist_id, product_id);');
            }
            if ($table === 'coupon_product') {
                DB::statement('alter table coupon_product add primary key (coupon_id, product_id);');
            }
            if ($table === 'reviews') {
                DB::statement('create index reviews_product_id_status_index on reviews (product_id, status);');
                DB::statement('create index reviews_user_id_product_id_index on reviews (user_id, product_id);');
            }
            DB::statement(
                "alter table {$table} add constraint {$table}_product_id_foreign
                 foreign key (product_id) references products (id) on delete {$onDelete};"
            );
        }

        if ($productsHasUuid && $productsHasUuid->exists) {
            DB::statement('alter table products drop constraint if exists products_pkey;');
            DB::statement('alter table products add column if not exists id bigserial;');
            DB::statement('alter table products add primary key (id);');
            DB::statement('create unique index if not exists products_uuid_unique on products (uuid);');
        }
    }
};
