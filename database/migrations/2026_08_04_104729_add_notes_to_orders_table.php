<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `notes` was already referenced by OrderResource, StoreOrderRequest,
 * UpdateOrderRequest and OrderController@store, but the column was never
 * created — so the API accepted a note and silently discarded it (and seeding
 * one hard-failed, because db:seed unguards models). This adds the missing
 * column so those paths actually work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
