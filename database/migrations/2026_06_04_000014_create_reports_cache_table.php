<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports_cache', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->string('metric_key');
            $table->decimal('metric_value', 14, 4);
            $table->enum('granularity', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->timestamps();

            $table->unique(['report_date', 'metric_key', 'granularity'], 'reports_cache_unique');
            $table->index(['report_date', 'granularity']);
            $table->index('metric_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports_cache');
    }
};
