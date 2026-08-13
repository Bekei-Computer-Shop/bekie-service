<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $connection = Schema::getConnection();
            $hasCreatedAtIndex = $connection->selectOne("SELECT to_regclass('public.order_items_created_at_index') as index_name")?->index_name !== null;
            $hasProductIdIndex = $connection->selectOne("SELECT to_regclass('public.order_items_product_id_index') as index_name")?->index_name !== null;

            if (! $hasCreatedAtIndex) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }

            if (! $hasProductIdIndex) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index('product_id');
                });
            }

            return;
        }

        try {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('created_at');
                $table->index('product_id');
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'already exists')) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['product_id']);
        });
    }
};
