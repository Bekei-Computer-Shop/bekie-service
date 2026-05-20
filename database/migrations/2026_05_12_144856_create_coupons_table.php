<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | IDENTIFICATION
            |--------------------------------------------------------------------------
            */
            $table->string('code')->unique(); // SAVE10, NEWUSER50

            /*
            |--------------------------------------------------------------------------
            | TYPE OF DISCOUNT
            |--------------------------------------------------------------------------
            */
            $table->enum('type', [
                'fixed',      // $10 off
                'percentage', // 10% off
            ]);

            $table->decimal('value', 10, 2); // 10 or 10%

            /*
            |--------------------------------------------------------------------------
            | USAGE LIMITS
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('usage_limit')->nullable(); // total usage limit
            $table->unsignedInteger('used_count')->default(0);  // current usage

            $table->unsignedInteger('user_limit')->nullable(); // per user limit

            /*
            |--------------------------------------------------------------------------
            | VALIDITY PERIOD
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | CONDITIONS (FLEXIBLE RULES)
            |--------------------------------------------------------------------------
            */
            $table->decimal('min_order_amount', 10, 2)->nullable();

            $table->decimal('max_discount_amount', 10, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | SCOPE (optional advanced targeting)
            |--------------------------------------------------------------------------
            */
            $table->json('applicable_products')->nullable();
            $table->json('applicable_categories')->nullable();

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */
            $table->softDeletes();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index(['code', 'is_active']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};