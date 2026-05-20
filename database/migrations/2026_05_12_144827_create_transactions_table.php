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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Transaction Identity (CRITICAL for auditing)
            |--------------------------------------------------------------------------
            */

            $table->string('transaction_reference')->unique();

            /*
            |--------------------------------------------------------------------------
            | Type of Transaction
            |--------------------------------------------------------------------------
            */

            $table->string('type');

            // payment
            // refund
            // chargeback
            // adjustment
            // payout

            /*
            |--------------------------------------------------------------------------
            | Money Flow
            |--------------------------------------------------------------------------
            */

            $table->decimal('amount', 15, 2);

            $table->string('currency')->default('USD');

            $table->enum('direction', ['debit', 'credit']);

            // debit  = money going out
            // credit = money coming in

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('pending');

            // pending
            // completed
            // failed
            // reversed

            /*
            |--------------------------------------------------------------------------
            | Balance Tracking (optional but powerful)
            |--------------------------------------------------------------------------
            */

            $table->decimal('balance_before', 15, 2)->nullable();

            $table->decimal('balance_after', 15, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | External Reference
            |--------------------------------------------------------------------------
            */

            $table->string('provider')->nullable();

            $table->string('provider_transaction_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata / Audit
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')->nullable();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Security / Traceability
            |--------------------------------------------------------------------------
            */

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('payment_id');

            $table->index('order_id');

            $table->index('user_id');

            $table->index('type');

            $table->index('status');

            $table->index('transaction_reference');

            $table->index('provider_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};