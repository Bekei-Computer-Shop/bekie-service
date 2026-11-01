<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Reports\ReportPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    /** Orders in these states never earned anything, so they are not income. */
    private const EXCLUDED_STATUSES = ['rejected', 'cancelled'];

    private const TOP_PRODUCTS = 10;

    private const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /**
     * Build the sales dashboard.
     *
     * Income is each live order's grand total, the figure the customer was
     * charged. Cost comes from the cost price frozen on each order line when it
     * was sold, so later price changes cannot rewrite history.
     *
     * @param  array{preset?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $period = ReportPeriod::resolve($filters);
        $previous = $period->previous();

        $orders = $this->orders($period);
        $current = $this->totals($orders);
        $before = $this->totals($this->orders($previous));

        $byBucket = $orders->groupBy(fn (array $order): string => $period->keyFor($order['at']));
        $series = collect($period->buckets())->map(function (array $bucket) use ($byBucket): array {
            return $bucket + $this->totals($byBucket->get($bucket['key'], collect()));
        });

        return [
            'summary' => [
                'revenue' => $current['revenue'],
                'cogs' => $current['cogs'],
                'profit' => $current['profit'],
                'margin' => $this->margin($current),
                'orders' => $current['orders'],
                'average_order_value' => $current['orders'] > 0 ? round($current['revenue'] / $current['orders'], 2) : 0.0,
                'revenue_change' => $this->change($current['revenue'], $before['revenue']),
                'cogs_change' => $this->change($current['cogs'], $before['cogs']),
                'profit_change' => $this->change($current['profit'], $before['profit']),
                'orders_change' => $this->change((float) $current['orders'], (float) $before['orders']),
                'margin_change' => $before['revenue'] > 0 ? round($this->margin($current) - $this->margin($before), 1) : null,
            ],
            'chart' => $series->map(fn (array $row): array => [
                'label' => $row['label'],
                'title' => $row['title'],
                'revenue' => $row['revenue'],
                'profit' => $row['profit'],
                'orders' => $row['orders'],
            ])->all(),
            'daily_breakdown' => $this->weekdayBreakdown($orders),
            'top_products' => $this->topProducts($period),
            'table' => $this->table($series),
            'meta' => $period->meta() + ['generated_at' => now()->toIso8601String()],
        ];
    }

    /**
     * Every live order in the window with its income and cost.
     *
     * @return Collection<int, array{at: \Carbon\CarbonImmutable, revenue: float, cogs: float}>
     */
    private function orders(ReportPeriod $period): Collection
    {
        $cogs = $this->liveOrders($period)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('order_items.deleted_at')
            ->groupBy('order_items.order_id')
            ->selectRaw('order_items.order_id, SUM(COALESCE(order_items.cost_price, 0) * order_items.quantity) as cogs')
            ->pluck('cogs', 'order_id');

        return $this->liveOrders($period)
            ->select(['orders.id', 'orders.created_at', 'orders.grand_total'])
            ->get()
            ->map(fn (object $order): array => [
                'at' => $period->localize((string) $order->created_at),
                'revenue' => (float) $order->grand_total,
                'cogs' => (float) ($cogs[$order->id] ?? 0),
            ]);
    }

    /** Orders that count as sales: not rejected, not cancelled, not deleted. */
    private function liveOrders(ReportPeriod $period): Builder
    {
        return DB::table('orders')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES)
            ->where('orders.created_at', '>=', $period->startUtc())
            ->where('orders.created_at', '<', $period->endExclusiveUtc());
    }

    /**
     * @param  Collection<int, array{revenue: float, cogs: float}>  $orders
     * @return array{revenue: float, cogs: float, profit: float, orders: int}
     */
    private function totals(Collection $orders): array
    {
        $revenue = round((float) $orders->sum('revenue'), 2);
        $cogs = round((float) $orders->sum('cogs'), 2);

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => round($revenue - $cogs, 2),
            'orders' => $orders->count(),
        ];
    }

    /** @param  array{revenue: float, profit: float}  $totals */
    private function margin(array $totals): float
    {
        return $totals['revenue'] > 0 ? round(($totals['profit'] / $totals['revenue']) * 100, 1) : 0.0;
    }

    /** Percent change, or null when there is nothing earlier to compare against. */
    private function change(float $current, float $previous): ?float
    {
        return $previous == 0.0 ? null : round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * Income and profit for each weekday, Monday first, quiet days included.
     *
     * @param  Collection<int, array{at: \Carbon\CarbonImmutable, revenue: float, cogs: float}>  $orders
     * @return list<array{label: string, revenue: float, profit: float, orders: int}>
     */
    private function weekdayBreakdown(Collection $orders): array
    {
        $byDay = $orders->groupBy(fn (array $order): int => $order['at']->isoWeekday());

        return collect(self::WEEKDAYS)->map(function (string $label, int $day) use ($byDay): array {
            $totals = $this->totals($byDay->get($day, collect()));

            return ['label' => $label, 'revenue' => $totals['revenue'], 'profit' => $totals['profit'], 'orders' => $totals['orders']];
        })->values()->all();
    }

    /**
     * Best sellers by sales value, ties broken by units sold.
     *
     * @return list<array{rank: int, name: string, units: int, revenue: float, share: float}>
     */
    private function topProducts(ReportPeriod $period): array
    {
        $lines = $this->liveOrders($period)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('order_items.deleted_at');

        $allSales = (float) (clone $lines)->sum('order_items.total');

        return (clone $lines)
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.total) as revenue')
            ->orderByDesc('revenue')
            ->orderByDesc('units')
            ->orderBy('order_items.product_name')
            ->limit(self::TOP_PRODUCTS)
            ->get()
            ->values()
            ->map(fn (object $product, int $index): array => [
                'rank' => $index + 1,
                'name' => (string) $product->product_name,
                'units' => (int) $product->units,
                'revenue' => round((float) $product->revenue, 2),
                'share' => $allSales > 0 ? round(((float) $product->revenue / $allSales) * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * One row per bucket; `revenue_change` compares each with the row before it.
     *
     * @param  Collection<int, array<string, mixed>>  $series
     * @return list<array<string, mixed>>
     */
    private function table(Collection $series): array
    {
        $previousRevenue = null;

        return $series->map(function (array $row) use (&$previousRevenue): array {
            $change = $previousRevenue === null ? null : $this->change($row['revenue'], $previousRevenue);
            $previousRevenue = $row['revenue'];

            return [
                'period' => $row['title'],
                'date_range' => $row['date_range'],
                'orders' => $row['orders'],
                'revenue' => $row['revenue'],
                'cogs' => $row['cogs'],
                'profit' => $row['profit'],
                'margin' => $this->margin($row),
                'revenue_change' => $change,
            ];
        })->all();
    }
}
