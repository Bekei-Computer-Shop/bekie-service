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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('cart_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */

            $table->integer('quantity')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Price Snapshot (CRITICAL)
            |--------------------------------------------------------------------------
            */

            $table->decimal('unit_price', 15, 2);

            $table->decimal('sale_price', 15, 2)->nullable();

            $table->decimal('cost_price', 15, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Calculated Fields (Optional but useful)
            |--------------------------------------------------------------------------
            */

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('discount', 15, 2)->default(0);

            $table->decimal('total', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Product Snapshot (important for historical consistency)
            |--------------------------------------------------------------------------
            */

            $table->string('product_name');

            $table->string('product_sku')->nullable();

            $table->string('variant_name')->nullable();

            $table->json('variant_attributes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_available')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')->nullable();

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

            $table->index('cart_id');

            $table->index('product_id');

            $table->index('product_variant_id');

            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};