<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('content_items', 'slug')) {
            return;
        }

        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('content_items', 'slug')) {
            return;
        }

        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
