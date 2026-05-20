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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->string('slug')->unique();

            $table->string('sku')->unique();

            $table->string('barcode')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            $table->text('short_description')->nullable();

            $table->longText('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            $table->decimal('price', 15, 2)->default(0);

            $table->decimal('sale_price', 15, 2)->nullable();

            $table->decimal('cost_price', 15, 2)->default(0);

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
            | Physical Product Info
            |--------------------------------------------------------------------------
            */

            $table->decimal('weight', 10, 2)->nullable();

            $table->decimal('length', 10, 2)->nullable();

            $table->decimal('width', 10, 2)->nullable();

            $table->decimal('height', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Product Media
            |--------------------------------------------------------------------------
            */

            $table->string('thumbnail')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SEO
            |--------------------------------------------------------------------------
            */

            $table->string('meta_title')->nullable();

            $table->text('meta_description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Product Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);

            $table->boolean('is_featured')->default(false);

            $table->boolean('is_digital')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Product Metrics
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('views_count')->default(0);

            $table->unsignedBigInteger('sales_count')->default(0);

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

            $table->index('category_id');

            $table->index('brand_id');

            $table->index('slug');

            $table->index('sku');

            $table->index('price');

            $table->index('is_active');

            $table->index('is_featured');

            $table->index('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};