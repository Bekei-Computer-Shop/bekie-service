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
        Schema::create('product_serial_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_serial_id')->constrained('product_serials')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_serial_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serial_histories');
    }
};
