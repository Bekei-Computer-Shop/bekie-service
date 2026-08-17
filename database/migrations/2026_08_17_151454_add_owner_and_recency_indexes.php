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
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_index');
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->index(['user_id', 'is_active', 'is_default', 'id'], 'addresses_user_active_default_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_user_id_created_at_index');
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropIndex('addresses_user_active_default_id_index');
        });
    }
};
