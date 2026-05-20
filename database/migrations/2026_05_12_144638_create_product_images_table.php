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
        Schema::create('product_images', function (Blueprint $table) {
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
            | Image Information
            |--------------------------------------------------------------------------
            */

            $table->string('image');

            $table->string('disk')->default('public');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Image Metadata
            |--------------------------------------------------------------------------
            */

            $table->string('alt_text')->nullable();

            $table->string('title')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Image Types
            |--------------------------------------------------------------------------
            */

            $table->enum('type', [
                'thumbnail',
                'gallery',
                'banner',
                'zoom'
            ])->default('gallery');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_primary')->default(false);

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

            $table->index('type');

            $table->index('is_primary');

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};