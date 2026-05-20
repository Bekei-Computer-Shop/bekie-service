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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Hierarchical Categories
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Basic Information
            $table->string('name');
            $table->string('slug')->unique();

            // Content
            $table->text('description')->nullable();

            // Media
            $table->string('image')->nullable();
            $table->string('icon')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Sorting
            $table->integer('sort_order')->default(0);

            // Soft Deletes
            $table->softDeletes();

            $table->timestamps();

            // Indexes
            $table->index('parent_id');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};