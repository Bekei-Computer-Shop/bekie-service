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
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_serialized')->default(false)->after('track_inventory');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('is_serialized')->default(false)->after('track_inventory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('is_serialized');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_serialized');
        });
    }
};
