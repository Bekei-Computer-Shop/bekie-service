<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_migration_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_system')->default('legacy-ecom'); // System identifier
            $table->string('table_name'); // e.g., 'products', 'orders'
            $table->string('source_id'); // Original ID in source system
            $table->string('target_id'); // New ID in target system
            $table->string('source_type')->nullable(); // 'integer', 'uuid', etc.
            $table->string('target_type')->nullable(); // 'integer', 'uuid', etc.
            $table->json('metadata')->nullable(); // Extra data (e.g., migration batch, strategy used)
            $table->timestamp('migrated_at')->useCurrent();

            $table->unique(['source_system', 'table_name', 'source_id']);
            $table->index(['table_name', 'target_id']);
            $table->index('migrated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_migration_map');
    }
};
