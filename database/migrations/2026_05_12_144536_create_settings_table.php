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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Grouping
            $table->string('group')->default('general');

            // Setting Key
            $table->string('key')->unique();

            // Value
            $table->longText('value')->nullable();

            // Data Type
            $table->enum('type', [
                'string',
                'integer',
                'boolean',
                'float',
                'json',
                'text'
            ])->default('string');

            // Description
            $table->text('description')->nullable();

            // Public visibility
            $table->boolean('is_public')->default(false);

            // Autoload for caching
            $table->boolean('autoload')->default(true);

            // Multi-tenant support
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('group');
            $table->index('autoload');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};