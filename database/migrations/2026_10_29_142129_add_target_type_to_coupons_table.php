<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which single target a promotion uses. Null is a legacy
     * untargeted coupon applied to the whole order by code.
     *
     * Existing rows are backfilled from their pivots, products first. A legacy
     * row with mixed targets keeps every pivot row, so checkout prices it
     * exactly as before; its other targets are only cleared the next time an
     * admin saves its targeting.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('target_type', 32)->nullable()->after('kind');
        });

        $backfill = [
            'products' => ['coupon_product', 'coupon_product_variant'],
            'categories' => ['coupon_category'],
            'customer_groups' => ['coupon_customer_group'],
        ];

        foreach ($backfill as $targetType => $pivots) {
            DB::table('coupons')
                ->whereNull('target_type')
                ->where(function (Builder $query) use ($pivots): void {
                    foreach ($pivots as $pivot) {
                        $query->orWhereExists(fn (Builder $exists) => $exists
                            ->select(DB::raw(1))
                            ->from($pivot)
                            ->whereColumn("{$pivot}.coupon_id", 'coupons.id'));
                    }
                })
                ->update(['target_type' => $targetType]);
        }
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn('target_type');
        });
    }
};
