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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->string('slug')->unique();

            // Branding
            $table->string('logo')->nullable();
            $table->string('website')->nullable();

            // Content
            $table->text('description')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Social Media
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('twitter')->nullable();
            $table->string('youtube')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Sorting
            $table->integer('sort_order')->default(0);

            // Analytics
            $table->unsignedBigInteger('products_count')->default(0);

            // Soft Deletes
            $table->softDeletes();

            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};