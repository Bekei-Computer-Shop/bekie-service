<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->index(['customer_id', 'status', 'warranty_end_at']);
            $table->index(['status', 'warranty_end_at']);
            $table->index('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status', 'warranty_end_at']);
            $table->dropIndex(['status', 'warranty_end_at']);
            $table->dropIndex(['purchased_at']);
        });
    }
};
