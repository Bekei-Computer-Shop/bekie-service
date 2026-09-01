<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerOrdersReportService
{
    /**
     * @param  array{preset?: string, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{table: list<array>, meta: array}
     */
    public function build(array $filters = []): array
    {
        [$startDate, $endDate, $groupBy] = $this->resolveRange($filters);

        $periodExpression = $this->periodExpression($groupBy, DB::connection()->getDriverName(), false);
        $periodExpressionO = $this->periodExpression($groupBy, DB::connection()->getDriverName(), true);

        // Get all orders in the date range with aggregation per period
        $periodStats = DB::table('orders')
            ->whereBetween('orders.created_at', [$startDate->toDateTimeString(), $endDate->endOfDay()->toDateTimeString()])
            ->whereNotIn('orders.status', ['rejected', 'cancelled'])
            ->selectRaw($periodExpression.' as period')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
            ->selectRaw('COUNT(DISTINCT orders.user_id) as unique_customers')
            ->selectRaw('SUM(order_items.quantity) as total_items')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->groupByRaw($periodExpression)
            ->orderBy('period')
            ->get();

        // For each period, count new customers (customers whose first order is in that period)
        $newCustomersPerPeriod = DB::table('orders as o')
            ->whereBetween('o.created_at', [$startDate->toDateTimeString(), $endDate->endOfDay()->toDateTimeString()])
            ->whereNotIn('o.status', ['rejected', 'cancelled'])
            ->selectRaw($periodExpressionO.' as period')
            ->selectRaw('COUNT(DISTINCT o.user_id) as new_customers_count')
            ->whereRaw('o.id = (SELECT MIN(id) FROM orders WHERE user_id = o.user_id AND status NOT IN (\'rejected\', \'cancelled\'))')
            ->groupByRaw($periodExpressionO)
            ->get();

        $newCustomersMap = $newCustomersPerPeriod->keyBy('period')->map->new_customers_count->toArray();

        $table = [];
        foreach ($periodStats as $stat) {
            $period = $stat->period;
            $ordersCount = (int) $stat->order_count;
            $uniqueCustomers = (int) $stat->unique_customers;
            $newCustomers = (int) ($newCustomersMap[$period] ?? 0);
            $returningCustomers = max(0, $uniqueCustomers - $newCustomers);
            $totalItems = (int) ($stat->total_items ?? 0);
            $avgItems = $ordersCount > 0 ? round($totalItems / $ordersCount, 1) : 0;

            $table[] = [
                'period' => $this->formatPeriodLabel($period, $groupBy, $startDate, $endDate),
                'orders' => $ordersCount,
                'uniqueCustomers' => $uniqueCustomers,
                'newCustomers' => $newCustomers,
                'returningCustomers' => $returningCustomers,
                'avgItems' => (float) $avgItems,
            ];
        }

        return [
            'table' => $table,
            'meta' => [
                'preset' => $filters['preset'] ?? 'custom',
                'date_from' => $startDate->toDateString(),
                'date_to' => $endDate->toDateString(),
            ],
        ];
    }

    private function periodExpression(string $groupBy, string $driver, bool $useAlias = false): string
    {
        $table = $useAlias ? 'o' : 'orders';

        return match ($driver) {
            'pgsql' => match ($groupBy) {
                '%Y-%m-%d' => "to_char({$table}.created_at, 'YYYY-MM-DD')",
                '%Y-%m' => "to_char({$table}.created_at, 'YYYY-MM')",
                '%x-%v' => "to_char({$table}.created_at, 'IYYY-IW')",
                '%Y' => "to_char({$table}.created_at, 'YYYY')",
                default => "to_char({$table}.created_at, 'YYYY-MM-DD')",
            },
            'mysql' => match ($groupBy) {
                '%Y-%m-%d' => "DATE({$table}.created_at)",
                '%Y-%m' => "DATE_FORMAT({$table}.created_at, '%Y-%m')",
                '%x-%v' => "DATE_FORMAT({$table}.created_at, '%x-%v')",
                '%Y' => "DATE_FORMAT({$table}.created_at, '%Y')",
                default => "DATE({$table}.created_at)",
            },
            'sqlite' => match ($groupBy) {
                '%Y-%m-%d' => "strftime('%Y-%m-%d', {$table}.created_at)",
                '%Y-%m' => "strftime('%Y-%m', {$table}.created_at)",
                '%x-%v' => "strftime('%Y-%W', {$table}.created_at)",
                '%Y' => "strftime('%Y', {$table}.created_at)",
                default => "strftime('%Y-%m-%d', {$table}.created_at)",
            },
            default => match ($groupBy) {
                '%Y-%m-%d' => "DATE({$table}.created_at)",
                '%Y-%m' => "DATE_FORMAT({$table}.created_at, '%Y-%m')",
                '%x-%v' => "DATE_FORMAT({$table}.created_at, '%x-%v')",
                '%Y' => "DATE_FORMAT({$table}.created_at, '%Y')",
                default => "DATE({$table}.created_at)",
            },
        };
    }

    private function formatPeriodLabel(string $period, string $groupBy, Carbon $startDate, Carbon $endDate): string
    {
        if ($groupBy === '%Y-%m-%d') {
            return Carbon::parse($period)->format('M d');
        }

        if ($groupBy === '%Y-%m') {
            return Carbon::parse($period)->format('M');
        }

        if ($groupBy === '%x-%v' || $groupBy === '%Y-%W') {
            // Week format: calculate start and end dates
            $year = (int) substr($period, 0, 4);
            $week = (int) substr($period, -2);

            $weekStart = Carbon::parse($year.'W'.sprintf('%02d', $week))->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            return $weekStart->format('M d').' – '.$weekEnd->format('M d');
        }

        if ($groupBy === '%Y') {
            return $period;
        }

        return $period;
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
            $startDate = Carbon::parse($dateFrom ?? now()->subMonths(11)->startOfMonth()->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->endOfMonth()->toDateString());

            return [$startDate, $endDate, '%Y-%m', 'Y-m'];
        }

        if ($preset === 'yearly') {
            $startDate = Carbon::parse($dateFrom ?? now()->subYears(4)->startOfYear()->toDateString());
            $endDate = Carbon::parse($dateTo ?? now()->endOfYear()->toDateString());

            return [$startDate, $endDate, '%Y', 'Y'];
        }

        $startDate = Carbon::parse($dateFrom ?? now()->subMonths(1)->toDateString());
        $endDate = Carbon::parse($dateTo ?? now()->toDateString());

        return [$startDate, $endDate, '%Y-%m-%d', 'Y-m-d'];
    }
}
