<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders kept the shipping method only as text (`shipping_provider`) and a fee
 * (`shipping_total`). This links back to the method that was chosen so the
 * order can name it by id. The name and fee columns stay the snapshot to
 * trust: the method can be renamed, repriced or deleted later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // No `constrained()` — see add_coupon_to_orders_table: an FK on an
            // existing table forces a full rebuild under SQLite. Existing rows
            // stay null.
            $table->unsignedBigInteger('shipping_method_id')->nullable()->after('shipping_total');

            $table->index('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_method_id']);
            $table->dropColumn('shipping_method_id');
        });
    }
};
