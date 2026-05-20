<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | REVIEW CONTENT
            |--------------------------------------------------------------------------
            */
            $table->unsignedTinyInteger('rating'); // 1–5 stars
            $table->string('title')->nullable();
            $table->text('comment')->nullable();

            /*
            |--------------------------------------------------------------------------
            | MODERATION SYSTEM
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'hidden',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | VERIFICATION (VERY IMPORTANT FOR TRUST)
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_verified_purchase')->default(false);

            /*
            |--------------------------------------------------------------------------
            | HELPFULNESS VOTING (like Amazon "helpful" system)
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);

            /*
            |--------------------------------------------------------------------------
            | MEDIA SUPPORT
            |--------------------------------------------------------------------------
            */
            $table->json('images')->nullable(); // review photos

            /*
            |--------------------------------------------------------------------------
            | ADMIN CONTROL
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_featured')->default(false);

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
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'product_id']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};