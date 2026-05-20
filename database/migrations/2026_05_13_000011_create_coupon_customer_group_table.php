<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_customer_group', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_customer_group');
    }
};
