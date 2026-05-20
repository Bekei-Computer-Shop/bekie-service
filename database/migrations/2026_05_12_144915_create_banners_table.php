<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | BASIC CONTENT
            |--------------------------------------------------------------------------
            */
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();

            /*
            |--------------------------------------------------------------------------
            | MEDIA
            |--------------------------------------------------------------------------
            */
            $table->string('image_desktop')->nullable();
            $table->string('image_mobile')->nullable();

            /*
            |--------------------------------------------------------------------------
            | CALL TO ACTION (CTA)
            |--------------------------------------------------------------------------
            */
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();

            /*
            |--------------------------------------------------------------------------
            | DISPLAY SETTINGS
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | SCHEDULING (MARKETING CAMPAIGNS)
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | TARGETING (OPTIONAL ADVANCED FEATURE)
            |--------------------------------------------------------------------------
            */
            $table->string('position')->default('homepage'); 
            // homepage, product_page, checkout, sidebar

            $table->json('meta')->nullable(); 
            // flexible future config (colors, animation, etc.)

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */
            $table->softDeletes();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index(['is_active', 'position']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};