<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional inventory model: in addition to on-hand stock (`stock_quantity`)
 * and the minimum/reorder thresholds that already exist, a stockable now tracks
 * reserved, damaged and incoming units plus an optional upper bound.
 *
 * The brief's available-stock formula is `available = on_hand - reserved`, so
 * those two live side by side here. Columns are added to both products and
 * product variants because stock is polymorphic across the two (see the
 * stockable morph on stock_movements).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('reserved_stock')->default(0)->after('stock_quantity');
                $t->unsignedInteger('damaged_stock')->default(0)->after('reserved_stock');
                $t->unsignedInteger('incoming_stock')->default(0)->after('damaged_stock');
                $t->unsignedInteger('max_stock_level')->nullable()->after('min_stock_alert');
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'product_variants'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['reserved_stock', 'damaged_stock', 'incoming_stock', 'max_stock_level']);
            });
        }
    }
};
