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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Payment Identity (CRITICAL for idempotency)
            |--------------------------------------------------------------------------
            */

            $table->string('payment_reference')->unique();

            // Internal reference (e.g. PAY-2026-00001)

            $table->string('provider')->nullable();

            // stripe, paypal, cod, bank_transfer

            $table->string('provider_payment_id')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | Amounts (SNAPSHOT)
            |--------------------------------------------------------------------------
            */

            $table->decimal('amount', 15, 2);

            $table->decimal('currency_rate', 15, 6)->nullable();

            $table->string('currency')->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Payment Status (SEPARATE FROM ORDER STATUS)
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('pending');

            // pending
            // authorized
            // paid
            // failed
            // refunded
            // partially_refunded

            /*
            |--------------------------------------------------------------------------
            | Payment Method Details
            |--------------------------------------------------------------------------
            */

            $table->string('payment_method')->nullable();

            // card, bank_transfer, wallet, cod

            $table->string('card_brand')->nullable();

            $table->string('last4')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Gateway Data (IMPORTANT FOR DEBUGGING)
            |--------------------------------------------------------------------------
            */

            $table->json('gateway_response')->nullable();

            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Refund Tracking
            |--------------------------------------------------------------------------
            */

            $table->decimal('refunded_amount')->default(0);

            $table->timestamp('refunded_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lifecycle Events
            |--------------------------------------------------------------------------
            */

            $table->timestamp('authorized_at')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamp('failed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Security / Audit
            |--------------------------------------------------------------------------
            */

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Soft Deletes (for audit safety)
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('order_id');

            $table->index('user_id');

            $table->index('status');

            $table->index('provider');

            $table->index('provider_payment_id');

            $table->index('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};