<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a product was first published, for the storefront's "new arrival"
 * flag. Products only had `is_active`, which says whether a product is live
 * but not since when, and `created_at`, which a product drafted inactive and
 * activated later would get wrong. Product::booted() stamps this the first
 * time a product is saved active and never moves it afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('is_active');

            $table->index('published_at');
        });

        // Products already live were published at the latest when they were
        // created; that keeps every existing product from turning "new" on
        // deploy. Inactive products stay null until they are first activated.
        DB::table('products')
            ->where('is_active', true)
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
