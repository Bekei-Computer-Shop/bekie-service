<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('shipping_method_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * IMPORTANT:
             * Avoid constrained() here unless "carriers" table is guaranteed
             * to exist BEFORE this migration runs.
             */
            $table->foreignId('carrier_id')
                ->nullable()
                ->constrained('carriers')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | TRACKING
            |--------------------------------------------------------------------------
            */

            $table->string('tracking_number')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'processing',
                'packed',
                'shipped',
                'in_transit',
                'delivered',
                'failed',
                'returned',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | SHIPPING DETAILS
            |--------------------------------------------------------------------------
            */

            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->unsignedInteger('estimated_days')->nullable();

            /*
            |--------------------------------------------------------------------------
            | LOGISTICS TIMELINE
            |--------------------------------------------------------------------------
            */

            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | ADDRESS SNAPSHOT (IMMUTABLE)
            |--------------------------------------------------------------------------
            */

            $table->string('recipient_name');
            $table->string('phone')->nullable();

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();

            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES (OPTIMIZED FOR REAL USE)
            |--------------------------------------------------------------------------
            */

            $table->index(['order_id', 'status']);
            $table->index('status');
            $table->index('tracking_number');
            $table->index('created_at');
            $table->index('carrier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};