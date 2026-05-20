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
        Schema::create('carts', function (Blueprint $table) {
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

            /*
            |--------------------------------------------------------------------------
            | Guest Cart Support
            |--------------------------------------------------------------------------
            */

            $table->uuid('session_id')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | Cart Identity
            |--------------------------------------------------------------------------
            */

            $table->string('currency')->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('discount_total', 15, 2)->default(0);

            $table->decimal('tax_total', 15, 2)->default(0);

            $table->decimal('shipping_total', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Coupon / Promotion
            |--------------------------------------------------------------------------
            */

            $table->string('coupon_code')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cart Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'converted',
                'abandoned',
                'expired',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Device / Tracking
            |--------------------------------------------------------------------------
            */

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Recovery & Expiration
            |--------------------------------------------------------------------------
            */

            $table->timestamp('expires_at')->nullable();

            $table->timestamp('last_activity_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extra Metadata
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')->nullable();

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

            $table->index('session_id');

            $table->index('status');

            $table->index('expires_at');

            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};