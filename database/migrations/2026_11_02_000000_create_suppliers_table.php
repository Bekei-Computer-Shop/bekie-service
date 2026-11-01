<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Unique among live suppliers only (enforced in the form requests), so a
            // deleted supplier's code can be reused.
            $table->string('code', 64)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            // The store-wide fallback the stock form pre-selects for a supplier
            // delivery when the product has no default supplier. At most one row.
            $table->boolean('is_default')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
