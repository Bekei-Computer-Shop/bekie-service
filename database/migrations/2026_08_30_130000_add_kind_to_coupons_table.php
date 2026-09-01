<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of campaign a promotion is — a flash sale, a BOGO, free shipping —
 * as opposed to `type`, which is how its discount is computed.
 *
 * The two are deliberately separate columns. `Coupon::calculateDiscount()`
 * treats any `type` that is not 'fixed' as a percentage, so folding a campaign
 * label into that enum would make a $25 BOGO silently take 25% off every cart.
 *
 * A plain string rather than an enum: the admin form's list of campaigns is
 * expected to grow, and the allowed values are enforced by `Coupon::KINDS`
 * through the promotion form requests. That also avoids rewriting a CHECK
 * constraint on every driver — the suite runs on SQLite, production on Postgres.
 *
 * Nullable, and null for every existing row: a promotion with no campaign kind
 * is an ordinary discount, which is what all of them are today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('kind', 32)->nullable()->after('type');
            // The storefront will want "show me the running flash sales".
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
