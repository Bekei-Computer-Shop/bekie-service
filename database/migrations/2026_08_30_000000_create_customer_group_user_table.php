<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer-group membership pivot.
 *
 * `User::customerGroups()` and `CustomerGroup::users()` have both pointed at
 * `customer_group_user` since the models were written, but no migration ever
 * created it — any read of either relation failed with 42P01. This adds the
 * table those relations already assume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_group_user', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'user_id']);
            // Membership is looked up per user ("which groups is this customer
            // in?") far more often than per group, and the composite primary
            // key above cannot serve that direction.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_user');
    }
};
