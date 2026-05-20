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
        Schema::create('orders', function (Blueprint $table) {
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

            $table->foreignId('address_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Order Identity
            |--------------------------------------------------------------------------
            */

            $table->string('order_number')->unique();

            /*
            |--------------------------------------------------------------------------
            | Financial Snapshot (CRITICAL)
            |--------------------------------------------------------------------------
            */

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('discount_total', 15, 2)->default(0);

            $table->decimal('tax_total', 15, 2)->default(0);

            $table->decimal('shipping_total', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            $table->string('currency')->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            $table->string('payment_method')->nullable();

            // stripe, paypal, cod, bank_transfer

            $table->string('payment_status')->default('pending');

            // pending, paid, failed, refunded

            $table->string('transaction_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Order Status (IMPORTANT STATE MACHINE)
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('pending');

            // pending
            // confirmed
            // processing
            // shipped
            // delivered
            // cancelled
            // refunded

            /*
            |--------------------------------------------------------------------------
            | Shipping
            |--------------------------------------------------------------------------
            */

            $table->string('shipping_status')->default('pending');

            $table->string('tracking_number')->nullable();

            $table->string('shipping_provider')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Snapshot Data (VERY IMPORTANT)
            |--------------------------------------------------------------------------
            */

            $table->json('customer_snapshot')->nullable();

            $table->json('address_snapshot')->nullable();

            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamp('paid_at')->nullable();

            $table->timestamp('shipped_at')->nullable();

            $table->timestamp('delivered_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamp('refunded_at')->nullable();

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

            $table->index('order_number');

            $table->index('status');

            $table->index('payment_status');

            $table->index('transaction_id');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};