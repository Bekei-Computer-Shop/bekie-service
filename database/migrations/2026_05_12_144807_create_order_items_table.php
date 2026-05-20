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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

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
            | Calculated Values (Frozen at purchase time)
            |--------------------------------------------------------------------------
            */

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('discount', 15, 2)->default(0);

            $table->decimal('tax', 15, 2)->default(0);

            $table->decimal('total', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Product Snapshot (VERY IMPORTANT)
            |--------------------------------------------------------------------------
            */

            $table->string('product_name');

            $table->string('product_sku')->nullable();

            $table->string('variant_name')->nullable();

            $table->json('variant_attributes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Fulfillment Tracking
            |--------------------------------------------------------------------------
            */

            $table->integer('quantity_shipped')->default(0);

            $table->integer('quantity_refunded')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('pending');

            // pending
            // shipped
            // delivered
            // returned
            // refunded

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

            $table->index('order_id');

            $table->index('product_id');

            $table->index('product_variant_id');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};