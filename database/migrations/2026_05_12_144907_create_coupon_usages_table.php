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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->uuid('session_id')->nullable();
            $table->string('coupon_code');

            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->timestamp('used_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
            $table->index(['order_id']);
            $table->index(['session_id']);
            $table->index(['used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
