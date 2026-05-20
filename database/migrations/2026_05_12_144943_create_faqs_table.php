<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | CORE CONTENT
            |--------------------------------------------------------------------------
            */
            $table->string('question');
            $table->text('answer');

            /*
            |--------------------------------------------------------------------------
            | CATEGORIZATION (VERY IMPORTANT FOR SCALING)
            |--------------------------------------------------------------------------
            */
            $table->string('category')->nullable();
            // or later normalize into faq_categories table

            /*
            |--------------------------------------------------------------------------
            | VISIBILITY CONTROL
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_active')->default(true);

            $table->boolean('is_featured')->default(false);

            /*
            |--------------------------------------------------------------------------
            | SORTING (CMS CONTROL)
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | SEO SUPPORT (OPTIONAL BUT POWERFUL)
            |--------------------------------------------------------------------------
            */
            $table->string('slug')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | TRACKING / ANALYTICS (OPTIONAL ADVANCED)
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);

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
            $table->index(['is_active', 'category']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};