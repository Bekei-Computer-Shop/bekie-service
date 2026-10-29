<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Order;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\DemoOrderSeeder;
use Database\Seeders\PromotionSeeder;
use Database\Seeders\PromotionUsageOrderSeeder;
use Database\Seeders\Support\DemoData;
use Database\Seeders\ThisWeekOrderSeeder;
use Database\Seeders\VerifiedReviewSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Removes the demo orders and everything that only exists because of them,
 * then seeds a fresh, consistent set — all in one transaction, so a failure
 * at any point leaves the database exactly as it was.
 *
 * Only orders tagged by a demo seeder (`metadata->seeded_by`, see
 * DemoData::ORDER_TAGS) are removed. Every table with a foreign key to
 * `orders` is looked up first; rows in a table this command does not know
 * about that point at a demo order stop the reset instead of being lost.
 * Customers, products, promotions and anything created outside the seeders
 * are left alone, apart from the usage counters of promotions that lose
 * usage rows.
 */
class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Delete the seeded demo orders and their dependent records, then regenerate them (local/testing only)';

    /**
     * Tables with a foreign key to orders.id, and the column it uses. Rows in
     * any other referencing table block the reset.
     */
    private const DEPENDENTS = [
        'order_items' => 'order_id',
        'coupon_usages' => 'order_id',
        'payments' => 'order_id',
        'transactions' => 'order_id',
        'khqr_transactions' => 'order_id',
        'shipments' => 'order_id',
        'reviews' => 'order_id',
        'product_serials' => 'order_id',
        'product_serial_histories' => 'order_id',
    ];

    /** Re-seeded in this order; PromotionUsageOrderSeeder must come first. */
    private const SEEDERS = [
        PromotionSeeder::class,
        PromotionUsageOrderSeeder::class,
        CustomerSeeder::class,
        DemoOrderSeeder::class,
        ThisWeekOrderSeeder::class,
        VerifiedReviewSeeder::class,
    ];

    public function handle(): int
    {
        if (! DemoData::allowed()) {
            $this->error(sprintf(
                'demo:reset only runs when APP_ENV is %s (current: %s). Nothing was changed.',
                implode(' or ', DemoData::ENVIRONMENTS),
                app()->environment(),
            ));

            return self::FAILURE;
        }

        $orderIds = $this->demoOrderIds();

        try {
            $this->assertNoUnknownDependents($orderIds);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summarize($orderIds);

        if (! $this->option('force') && ! $this->confirm('Delete these demo records and regenerate the demo orders?')) {
            $this->line('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orderIds): void {
            $this->deleteDemoOrders($orderIds);

            foreach (self::SEEDERS as $seeder) {
                $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
            }

            PromotionSeeder::syncUsageCounts();
        });

        $this->info(sprintf(
            'Demo orders regenerated: %d orders now carry a demo tag.',
            Order::query()->withTrashed()->whereIn('metadata->seeded_by', DemoData::ORDER_TAGS)->count(),
        ));

        return self::SUCCESS;
    }

    /** @return Collection<int, int> */
    private function demoOrderIds(): Collection
    {
        return Order::query()
            ->withTrashed()
            ->whereIn('metadata->seeded_by', DemoData::ORDER_TAGS)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * Reads the live schema for every foreign key to `orders`, so a table
     * added after this command was written cannot have rows silently
     * cascaded away or orphaned.
     *
     * @param  Collection<int, int>  $orderIds
     */
    private function assertNoUnknownDependents(Collection $orderIds): void
    {
        if ($orderIds->isEmpty()) {
            return;
        }

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if ($name === 'orders' || isset(self::DEPENDENTS[$name])) {
                continue;
            }

            foreach (Schema::getForeignKeys($name) as $foreignKey) {
                if ($foreignKey['foreign_table'] !== 'orders') {
                    continue;
                }

                $column = $foreignKey['columns'][0];
                $rows = $this->countIn($name, $column, $orderIds);

                if ($rows > 0) {
                    throw new RuntimeException(sprintf(
                        '%d row(s) in %s.%s point at demo orders and demo:reset does not know how to handle that table. Nothing was changed.',
                        $rows,
                        $name,
                        $column,
                    ));
                }
            }
        }
    }

    /** @param Collection<int, int> $orderIds */
    private function summarize(Collection $orderIds): void
    {
        $rows = [['orders', $orderIds->count()]];
        foreach (self::DEPENDENTS as $table => $column) {
            if (Schema::hasTable($table)) {
                $rows[] = [$table, $this->countIn($table, $column, $orderIds)];
            }
        }

        $this->line('Demo records that will be removed (tags: '.implode(', ', DemoData::ORDER_TAGS).'):');
        $this->table(['Table', 'Rows'], $rows);
    }

    /** @param Collection<int, int> $orderIds */
    private function deleteDemoOrders(Collection $orderIds): void
    {
        foreach ($orderIds->chunk(500) as $chunk) {
            $ids = $chunk->values()->all();

            // Promotions keep their counters equal to their usage rows: take
            // back one use for each usage row removed.
            DB::table('coupon_usages')
                ->whereIn('order_id', $ids)
                ->selectRaw('coupon_id, count(*) as uses')
                ->groupBy('coupon_id')
                ->pluck('uses', 'coupon_id')
                ->each(function ($uses, $couponId): void {
                    $coupon = Coupon::withTrashed()->find($couponId);
                    if ($coupon !== null) {
                        $coupon->forceFill(['used_count' => max(0, (int) $coupon->used_count - (int) $uses)])->saveQuietly();
                    }
                });
            DB::table('coupon_usages')->whereIn('order_id', $ids)->delete();

            // Serial units created with a demo order go with it (their history
            // rows cascade). A unit from real stock that was assigned to a
            // demo order is kept and just loses the link, as the FK intends.
            DB::table('product_serial_histories')->whereIn('order_id', $ids)->delete();
            DB::table('product_serials')
                ->whereIn('order_id', $ids)
                ->where('receiving_reference', DemoData::ORDER_SERIAL_REFERENCE)
                ->delete();

            DB::table('reviews')->whereIn('order_id', $ids)->delete();

            foreach (['transactions', 'khqr_transactions', 'shipments', 'payments', 'order_items'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('order_id', $ids)->delete();
                }
            }

            DB::table('orders')->whereIn('id', $ids)->delete();
        }
    }

    /** @param Collection<int, int> $orderIds */
    private function countIn(string $table, string $column, Collection $orderIds): int
    {
        return $orderIds->chunk(500)->sum(
            fn (Collection $chunk): int => DB::table($table)->whereIn($column, $chunk->values()->all())->count()
        );
    }
}
