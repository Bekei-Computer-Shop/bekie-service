<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links products to the promotions that apply to them.
 *
 * The admin "promotions" API is served by the `Coupon` model (see
 * PromotionController), not the near-unused `Promotion` model, so the pivot
 * joins `coupons` — hence the Laravel-conventional `coupon_product` name.
 *
 * `coupons.applicable_products` is a JSON column covering roughly the same
 * ground, but nothing in the codebase reads it and a JSON array can't be
 * joined, indexed, or edited from the product side without a read-modify-write
 * race. It is left untouched here rather than migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_product', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
            // The product form reads "which promotions apply to this product",
            // the reverse of the primary key's leading column.
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product');
    }
};
