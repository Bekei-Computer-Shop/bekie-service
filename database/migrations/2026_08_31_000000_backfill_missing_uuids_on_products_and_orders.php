<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The 2026_06_04 migration added `uuid` to products and orders and backfilled
 * the rows that existed then. But the Model `creating` hooks that populate the
 * column were only added two weeks later (2026-06-19), so anything seeded or
 * inserted in that window kept a NULL uuid.
 *
 * Both models route-bind on `uuid` (getRouteKeyName), so on Postgres a detail
 * request for one of those rows sends the integer id into a `where uuid = ?`
 * against a `uuid` column and blows up with a 22P02 "invalid input syntax for
 * type uuid" — a 500 on the order/product detail screens. This backfills the
 * stragglers so every row is reachable by its route key again.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'orders'] as $table) {
            DB::table($table)->whereNull('uuid')->orderBy('id')->cursor()->each(
                fn ($row) => DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()])
            );
        }
    }

    public function down(): void
    {
        // No-op: a backfill of previously-NULL values cannot be meaningfully
        // reversed, and the columns stay nullable.
    }
};
