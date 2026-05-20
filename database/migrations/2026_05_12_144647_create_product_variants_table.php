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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Variant Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            // Example:
            // Red / XL / 256GB
            $table->string('slug')->unique();

            /*
            |--------------------------------------------------------------------------
            | SKU & Barcode
            |--------------------------------------------------------------------------
            */

            $table->string('sku')->unique();

            $table->string('barcode')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            $table->decimal('price', 15, 2)->nullable();

            $table->decimal('sale_price', 15, 2)->nullable();

            $table->decimal('cost_price', 15, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            $table->integer('stock_quantity')->default(0);

            $table->integer('min_stock_alert')->default(5);

            $table->boolean('track_inventory')->default(true);

            $table->boolean('in_stock')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Physical Properties
            |--------------------------------------------------------------------------
            */

            $table->decimal('weight', 10, 2)->nullable();

            $table->decimal('length', 10, 2)->nullable();

            $table->decimal('width', 10, 2)->nullable();

            $table->decimal('height', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Variant Media
            |--------------------------------------------------------------------------
            */

            $table->string('image')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Variant Attributes
            |--------------------------------------------------------------------------
            */

            // Flexible JSON:
            // {
            //   "color": "Red",
            //   "size": "XL"
            // }

            $table->json('attributes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_default')->default(false);

            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Sorting
            |--------------------------------------------------------------------------
            */

            $table->integer('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Soft Deletes
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('product_id');

            $table->index('sku');

            $table->index('barcode');

            $table->index('is_default');

            $table->index('is_active');

            $table->index('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};