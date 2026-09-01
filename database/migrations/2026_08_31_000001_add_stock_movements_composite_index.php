<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock movement history is read back per stockable (product/variant) and is
 * always ordered newest-first, so the polymorphic pair + created_at is the hot
 * query path. The existing single-column indexes do not serve it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(
                ['stockable_type', 'stockable_id', 'created_at'],
                'stock_movements_stockable_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_stockable_created_idx');
        });
    }
};
