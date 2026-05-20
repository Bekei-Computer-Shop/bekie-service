<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | OWNER (USER OR GUEST)
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Guest session support (NO duplicate index definition)
            $table->uuid('session_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | WISHLIST DETAILS
            |--------------------------------------------------------------------------
            */

            $table->string('name')->default('My Wishlist');

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | VISIBILITY
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | METADATA (FUTURE EXTENSION)
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES (CLEAN + SAFE)
            |--------------------------------------------------------------------------
            */

            $table->index(['user_id', 'is_active']);

            $table->index(['session_id']);

            $table->index(['is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};