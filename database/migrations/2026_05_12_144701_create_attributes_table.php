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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            // Example:
            // color
            // size
            // storage

            $table->string('slug')->unique();

            /*
            |--------------------------------------------------------------------------
            | Attribute Type
            |--------------------------------------------------------------------------
            */

            $table->enum('type', [
                'text',
                'number',
                'boolean',
                'select',
                'multiselect',
                'color'
            ])->default('select');

            /*
            |--------------------------------------------------------------------------
            | UI / Display
            |--------------------------------------------------------------------------
            */

            $table->string('display_name')->nullable();

            $table->string('unit')->nullable();

            // Example:
            // GB
            // inch
            // cm

            /*
            |--------------------------------------------------------------------------
            | Behavior
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_required')->default(false);

            $table->boolean('is_filterable')->default(true);

            $table->boolean('is_searchable')->default(true);

            $table->boolean('is_variant')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            $table->json('validation_rules')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

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

            $table->index('slug');

            $table->index('type');

            $table->index('is_filterable');

            $table->index('is_variant');

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};