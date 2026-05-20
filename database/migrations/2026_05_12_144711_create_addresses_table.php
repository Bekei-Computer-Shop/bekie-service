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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Address Identity
            |--------------------------------------------------------------------------
            */

            $table->string('type')->default('shipping');

            // shipping
            // billing
            // office
            // warehouse
            // home

            $table->string('label')->nullable();

            // Example:
            // Home
            // Office
            // Main Warehouse

            /*
            |--------------------------------------------------------------------------
            | Receiver Information
            |--------------------------------------------------------------------------
            */

            $table->string('full_name');

            $table->string('phone')->nullable();

            $table->string('email')->nullable();

            $table->string('company')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Address Details
            |--------------------------------------------------------------------------
            */

            $table->string('address_line_1');

            $table->string('address_line_2')->nullable();

            $table->string('city');

            $table->string('state')->nullable();

            $table->string('postal_code')->nullable();

            $table->string('country')->default('Cambodia');

            /*
            |--------------------------------------------------------------------------
            | Geo Location
            |--------------------------------------------------------------------------
            */

            $table->decimal('latitude', 10, 7)->nullable();

            $table->decimal('longitude', 10, 7)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_default')->default(false);

            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Extra Metadata
            |--------------------------------------------------------------------------
            */

            $table->text('delivery_note')->nullable();

            $table->json('metadata')->nullable();

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

            $table->index('user_id');

            $table->index('type');

            $table->index('city');

            $table->index('country');

            $table->index('postal_code');

            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};