<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders recorded only an aggregate `discount_total` — there was no way to tell
 * which coupon (or promotion, since promotions are coupons here) produced it.
 * `coupon_usages` has an `order_id`, but nothing ever wrote a row to it.
 *
 * This stamps the coupon straight onto the order: `coupon_code` is the snapshot
 * that survives the coupon being edited or deleted, `coupon_id` links back to
 * the live record while it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // No `constrained()` — an FK added to an existing table forces a
            // full rebuild under SQLite (the test driver). The id is a soft
            // pointer; `coupon_code` is the durable snapshot.
            $table->unsignedBigInteger('coupon_id')->nullable()->after('discount_total');
            $table->string('coupon_code')->nullable()->after('coupon_id');

            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['coupon_id']);
            $table->dropColumn(['coupon_id', 'coupon_code']);
        });
    }
};
