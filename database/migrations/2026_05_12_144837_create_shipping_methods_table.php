<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name'); // e.g. "DHL Express"
            $table->string('code')->unique(); // e.g. "dhl_express"

            // Description
            $table->text('description')->nullable();

            // Pricing model
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('price_per_kg', 10, 2)->nullable();

            // Constraints
            $table->decimal('min_weight', 8, 2)->nullable();
            $table->decimal('max_weight', 8, 2)->nullable();

            // Delivery estimation
            $table->unsignedInteger('min_delivery_days')->nullable();
            $table->unsignedInteger('max_delivery_days')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Type (useful for extensibility: courier, pickup, digital, etc.)
            $table->string('type')->default('courier');

            // Sorting / display priority
            $table->unsignedInteger('sort_order')->default(0);

            // Soft delete for safer removal
            $table->softDeletes();

            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};