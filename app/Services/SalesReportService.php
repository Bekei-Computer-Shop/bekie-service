<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    /**
     * Build the sales dashboard from frozen order-item prices.
     *
     * @param  array{preset?: string, date_from?: string|null, date_to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        [$start, $end] = $this->resolveRange($filters);
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($start->diffInDays($end));

        $current = $this->rowsBetween($start, $end);
        $previous = $this->rowsBetween($previousStart, $previousEnd);
        $summary = $this->summary($current);
        $previousSummary = $this->summary($previous);

        return [
            'summary' => [
                'revenue' => $summary['revenue'],
                'cogs' => $summary['cogs'],
                'profit' => $summary['profit'],
                'margin' => $summary['revenue'] > 0 ? round(($summary['profit'] / $summary['revenue']) * 100, 1) : 0,
                'orders' => $summary['orders'],
                'revenue_change' => $this->change($summary['revenue'], $previousSummary['revenue']),
                'cogs_change' => $this->change($summary['cogs'], $previousSummary['cogs']),
                'profit_change' => $this->change($summary['profit'], $previousSummary['profit']),
                'margin_change' => $this->marginChange($summary, $previousSummary),
            ],
            'chart' => $this->chart($current, $filters['preset'] ?? 'weekly'),
            'daily_breakdown' => $this->dailyBreakdown($current),
            'top_products' => $this->topProducts($current),
            'table' => $this->table($current, $filters['preset'] ?? 'weekly'),
            'meta' => [
                'preset' => $filters['preset'] ?? 'custom',
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'previous_date_from' => $previousStart->toDateString(),
                'previous_date_to' => $previousEnd->toDateString(),
            ],
        ];
    }

    private function rowsBetween(Carbon $start, Carbon $end): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start->startOfDay(), $end->endOfDay()])
            ->whereNotIn('orders.status', ['rejected', 'cancelled'])
            ->select([
                'orders.id as order_id',
                'orders.created_at',
                'order_items.product_name',
                'order_items.quantity',
                'order_items.total',
                'order_items.cost_price',
            ])
            ->orderBy('orders.created_at')
            ->get();
    }

    private function summary(Collection $rows): array
    {
        $revenue = (float) $rows->sum(fn ($row) => (float) $row->total);
        $cogs = (float) $rows->sum(fn ($row) => (float) ($row->cost_price ?? 0) * (int) $row->quantity);

        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'profit' => round($revenue - $cogs, 2),
            'orders' => $rows->pluck('order_id')->unique()->count(),
        ];
    }

    private function chart(Collection $rows, string $preset): array
    {
        $groups = $rows->groupBy(function ($row) use ($preset) {
            $date = Carbon::parse($row->created_at);
            return match ($preset) {
                'daily' => $date->format('H:00'),
                'monthly' => $date->format('M'),
                'yearly' => $date->format('Y'),
                default => 'W'.$date->isoWeek,
            };
        });

        return $groups->map(function (Collection $items, string $label): array {
            $summary = $this->summary($items);
            return ['label' => $label, 'revenue' => $summary['revenue'], 'profit' => $summary['profit']];
        })->values()->all();
    }

    private function dailyBreakdown(Collection $rows): array
    {
        return $rows->groupBy(fn ($row) => Carbon::parse($row->created_at)->format('D'))
            ->map(function (Collection $items, string $label): array {
                $summary = $this->summary($items);
                return ['label' => $label, 'revenue' => $summary['revenue'], 'profit' => $summary['profit']];
            })->values()->all();
    }

    private function topProducts(Collection $rows): array
    {
        return $rows->groupBy('product_name')->map(function (Collection $items, string $name): array {
            $summary = $this->summary($items);
            return ['name' => $name, 'units' => (int) $items->sum('quantity'), 'revenue' => $summary['revenue']];
        })->sortByDesc('revenue')->take(5)->values()->all();
    }

    private function table(Collection $rows, string $preset): array
    {
        return $rows->groupBy(function ($row) use ($preset) {
            $date = Carbon::parse($row->created_at);
            return match ($preset) {
                'daily' => $date->format('Y-m-d'),
                'monthly' => $date->format('Y-m'),
                'yearly' => $date->format('Y'),
                default => $date->isoWeekYear.'-'.$date->isoWeek,
            };
        })->map(function (Collection $items, string $key): array {
            $summary = $this->summary($items);
            $first = Carbon::parse($items->min('created_at'));
            $last = Carbon::parse($items->max('created_at'));
            return [
                'period' => $key,
                'date_range' => $first->toDateString().' - '.$last->toDateString(),
                'orders' => $summary['orders'],
                'revenue' => $summary['revenue'],
                'cogs' => $summary['cogs'],
                'profit' => $summary['profit'],
                'margin' => $summary['revenue'] > 0 ? round(($summary['profit'] / $summary['revenue']) * 100, 1) : 0,
            ];
        })->values()->all();
    }

    private function change(float $current, float $previous): float
    {
        return $previous == 0 ? 0 : round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function marginChange(array $current, array $previous): float
    {
        $currentMargin = $current['revenue'] > 0 ? ($current['profit'] / $current['revenue']) * 100 : 0;
        $previousMargin = $previous['revenue'] > 0 ? ($previous['profit'] / $previous['revenue']) * 100 : 0;
        return round($currentMargin - $previousMargin, 1);
    }

    private function resolveRange(array $filters): array
    {
        $preset = $filters['preset'] ?? 'weekly';
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : null;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : null;

        if ($from && $to) return [$from, $to];

        return match ($preset) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfWeek()->subWeeks(9), now()->endOfWeek()],
        };
    }
}
