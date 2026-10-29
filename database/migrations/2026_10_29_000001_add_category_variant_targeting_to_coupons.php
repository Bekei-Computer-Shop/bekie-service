<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->boolean('include_subcategories')->default(false);
        });

        Schema::create('coupon_category', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('coupon_product_variant', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product_variant');
        Schema::dropIfExists('coupon_category');
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn('include_subcategories');
        });
    }
};
