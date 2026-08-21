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
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->text('description')->nullable()->after('code');
            $table->string('banner_image', 2048)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['name', 'description', 'banner_image']);
        });
    }
};
