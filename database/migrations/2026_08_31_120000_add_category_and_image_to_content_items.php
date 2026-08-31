<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * News rows (type `news`) are a mini-blog: the admin form collects a
     * category and a cover image alongside the title and body. Pages (type
     * `page`) simply leave both null.
     */
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('category', 100)->nullable()->after('body');
            $table->string('image_url', 2048)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropColumn(['category', 'image_url']);
        });
    }
};
