<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | CORE IDENTITY
            |--------------------------------------------------------------------------
            */
            $table->string('title');
            $table->string('slug')->unique();

            /*
            |--------------------------------------------------------------------------
            | CONTENT
            |--------------------------------------------------------------------------
            */
            $table->longText('content')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SEO FIELDS (VERY IMPORTANT)
            |--------------------------------------------------------------------------
            */
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS & PUBLISHING
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_published')->default(false);

            $table->timestamp('published_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | PAGE TYPE (CMS FLEXIBILITY)
            |--------------------------------------------------------------------------
            */
            $table->enum('type', [
                'page',
                'landing',
                'policy',
                'blog',
            ])->default('page');

            /*
            |--------------------------------------------------------------------------
            | TEMPLATE SUPPORT (ADVANCED)
            |--------------------------------------------------------------------------
            */
            $table->string('template')->nullable(); 
            // e.g. default, landing_v1, full_width

            /*
            |--------------------------------------------------------------------------
            | ORDERING (FOR CMS LISTING)
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | SOFT DELETE + AUDIT
            |--------------------------------------------------------------------------
            */
            $table->softDeletes();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index(['slug', 'is_published']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};