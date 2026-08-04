<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SoldProductsReportService
{
    /**
     * @param  array{preset?: string, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{labels: list<string>, series: list<array{name:string, data:list<int|float>}>}
     */
    public function build(array $filters = []): array
    {
        [$startDate, $endDate, $groupBy, $periodLabelFormat] = $this->resolveRange($filters);

        $periodExpression = $this->periodExpression($groupBy, DB::connection()->getDriverName());

        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate->toDateTimeString(), $endDate->endOfDay()->toDateTimeString()])
            ->whereNotIn('orders.status', ['rejected', 'cancelled'])
            ->selectRaw($periodExpression.' as period')
            ->selectRaw('products.id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('SUM(order_items.total) as revenue')
            ->groupByRaw($periodExpression)
            ->groupBy('products.id', 'products.name')
            ->orderBy('period')
            ->orderBy('product_name')
            ->get();

        $labels = [];
        $seriesMap = [];
        $periods = [];

        foreach ($rows as $row) {
            if (! in_array($row->period, $periods, true)) {
                $periods[] = $row->period;
            }

            $seriesMap[$row->product_name]['name'] = $row->product_name;
            $seriesMap[$row->product_name]['quantity'][$row->period] = (float) $row->quantity_sold;
            $seriesMap[$row->product_name]['revenue'][$row->period] = (float) $row->revenue;
        }

        $labels = $periods;

        $series = [];
        foreach ($seriesMap as $productName => $payload) {
            $quantityData = [];
            $revenueData = [];

            foreach ($periods as $period) {
                $quantityData[] = (float) ($payload['quantity'][$period] ?? 0);
                $revenueData[] = (float) ($payload['revenue'][$period] ?? 0);
            }

            $series[] = [
                'name' => $productName,
                'data' => $quantityData,
                'revenue_data' => $revenueData,
            ];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'meta' => [
                'preset' => $filters['preset'] ?? 'custom',
                'date_from' => $startDate->toDateString(),
                'date_to' => $endDate->toDateString(),
            ],
        ];
    }

    private function periodExpression(string $groupBy, string $driver): string
    {
        return match ($driver) {
            'pgsql' => match ($groupBy) {
                '%Y-%m-%d' => "to_char(orders.created_at, 'YYYY-MM-DD')",
                '%Y-%m' => "to_char(orders.created_at, 'YYYY-MM')",
                '%x-%v' => "to_char(orders.created_at, 'IYYY-IW')",
                '%Y' => "to_char(orders.created_at, 'YYYY')",
                default => "to_char(orders.created_at, 'YYYY-MM-DD')",
            },
            'mysql' => match ($groupBy) {
                '%Y-%m-%d' => "DATE(orders.created_at)",
                '%Y-%m' => "DATE_FORMAT(orders.created_at, '%Y-%m')",
                '%x-%v' => "DATE_FORMAT(orders.created_at, '%x-%v')",
                '%Y' => "DATE_FORMAT(orders.created_at, '%Y')",
                default => "DATE(orders.created_at)",
            },
            'sqlite' => match ($groupBy) {
                '%Y-%m-%d' => "strftime('%Y-%m-%d', orders.created_at)",
                '%Y-%m' => "strftime('%Y-%m', orders.created_at)",
                '%x-%v' => "strftime('%Y-%W', orders.created_at)",
                '%Y' => "strftime('%Y', orders.created_at)",
                default => "strftime('%Y-%m-%d', orders.created_at)",
            },
            default => match ($groupBy) {
                '%Y-%m-%d' => "DATE(orders.created_at)",
                '%Y-%m' => "DATE_FORMAT(orders.created_at, '%Y-%m')",
                '%x-%v' => "DATE_FORMAT(orders.created_at, '%x-%v')",
                '%Y' => "DATE_FORMAT(orders.created_at, '%Y')",
                default => "DATE(orders.created_at)",
            },
        };
    }

    private function resolveRange(array $filters): array
    {
        $preset = $filters['preset'] ?? 'custom';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($preset === 'daily') {
            $startDate = Carbon::parse($dateFrom ?? now()->subDays(6)->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->toDateString());
            return [$startDate, $endDate, '%Y-%m-%d', 'Y-m-d'];
        }

        if ($preset === 'weekly') {
            $startDate = Carbon::parse($dateFrom ?? now()->subWeeks(4)->startOfWeek()->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->endOfWeek()->toDateString());
            return [$startDate, $endDate, '%x-%v', 'Y-W'];
        }

        if ($preset === 'monthly') {
            $startDate = Carbon::parse($dateFrom ?? now()->subMonths(6)->startOfMonth()->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->endOfMonth()->toDateString());
            return [$startDate, $endDate, '%Y-%m', 'Y-m'];
        }

        if ($preset === 'yearly') {
            $startDate = Carbon::parse($dateFrom ?? now()->subYears(2)->startOfYear()->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->endOfYear()->toDateString());
            return [$startDate, $endDate, '%Y', 'Y'];
        }

        $startDate = Carbon::parse($dateFrom ?? now()->subMonths(1)->toDateString());
        $endDate = Carbon::parse($dateTo ?? now()->toDateString());

        return [$startDate, $endDate, '%Y-%m-%d', 'Y-m-d'];
    }
}
