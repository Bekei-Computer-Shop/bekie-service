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
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->integer('chunk_index')->default(0);
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->integer('records_migrated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};
