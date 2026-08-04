<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->morphs('stockable');
            $table->enum('movement_type', ['adjust', 'reconcile', 'stock_in', 'stock_out', 'transfer']);
            $table->integer('quantity');
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->string('reason')->nullable();
            $table->string('source_location')->nullable();
            $table->string('destination_location')->nullable();
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('movement_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
