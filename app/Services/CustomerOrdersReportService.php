<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Reports\ReportPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerOrdersReportService
{
    /** Orders in these states never happened as far as a customer's history goes. */
    private const EXCLUDED_STATUSES = ['rejected', 'cancelled'];

    /** Keeps the `user_id IN (...)` lookup well under the database's bind-parameter limit. */
    private const LOOKUP_CHUNK = 1000;

    /**
     * Orders and customers per period.
     *
     * A customer is "new" in the period that holds their first-ever order, and
     * "returning" in any later period they order in again. Guest orders count as
     * orders but not as customers, since there is no account to recognise.
     *
     * Grouping happens in PHP rather than with per-driver date SQL: production
     * runs Postgres and the tests run SQLite, and week numbering differs between
     * the two.
     *
     * @param  array{preset?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{table: list<array<string, mixed>>, totals: array<string, mixed>, meta: array<string, mixed>}
     */
    public function build(array $filters = []): array
    {
        $period = ReportPeriod::resolve($filters, hourly: false);

        $orders = $this->liveOrders($period)
            ->select(['orders.id', 'orders.user_id', 'orders.created_at', 'orders.grand_total'])
            ->get()
            ->map(fn (object $order): array => [
                'id' => (int) $order->id,
                'user_id' => $order->user_id === null ? null : (int) $order->user_id,
                'key' => $period->keyFor($period->localize((string) $order->created_at)),
                'revenue' => (float) $order->grand_total,
            ]);

        $itemsPerOrder = $this->liveOrders($period)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('order_items.deleted_at')
            ->groupBy('order_items.order_id')
            ->selectRaw('order_items.order_id, SUM(order_items.quantity) as items')
            ->pluck('items', 'order_id');

        $firstOrderAt = $this->firstOrderTimes($orders->pluck('user_id')->filter()->unique()->values());
        $firstBucket = array_map(
            fn (string $utc): string => $period->keyFor($period->localize($utc)),
            $firstOrderAt,
        );

        $byBucket = $orders->groupBy('key');
        $table = collect($period->buckets())->map(function (array $bucket) use ($byBucket, $itemsPerOrder, $firstBucket): array {
            /** @var Collection<int, array{id: int, user_id: int|null, key: string, revenue: float}> $inBucket */
            $inBucket = $byBucket->get($bucket['key'], collect());
            $customers = $inBucket->pluck('user_id')->filter()->unique();
            $new = $customers->filter(fn (int $id): bool => ($firstBucket[$id] ?? null) === $bucket['key'])->count();
            $orderCount = $inBucket->count();
            $items = (int) $inBucket->sum(fn (array $order): int => (int) ($itemsPerOrder[$order['id']] ?? 0));

            return [
                'period' => $bucket['title'],
                'label' => $bucket['label'],
                'date_range' => $bucket['date_range'],
                'orders' => $orderCount,
                'uniqueCustomers' => $customers->count(),
                'newCustomers' => $new,
                'returningCustomers' => $customers->count() - $new,
                'avgItems' => $orderCount > 0 ? round($items / $orderCount, 1) : 0.0,
                'revenue' => round((float) $inBucket->sum('revenue'), 2),
            ];
        })->values()->all();

        return [
            'table' => $table,
            'totals' => $this->totals($orders, $firstOrderAt, $period),
            'meta' => $period->meta(),
        ];
    }

    /** Orders that count: not rejected, not cancelled, not deleted, inside the window. */
    private function liveOrders(ReportPeriod $period): Builder
    {
        return DB::table('orders')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES)
            ->where('orders.created_at', '>=', $period->startUtc())
            ->where('orders.created_at', '<', $period->endExclusiveUtc());
    }

    /**
     * When each customer first ordered, looking at all time rather than just the
     * window, which is what separates a new customer from a returning one.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, string> user id => UTC timestamp
     */
    private function firstOrderTimes(Collection $userIds): array
    {
        $first = [];

        foreach ($userIds->chunk(self::LOOKUP_CHUNK) as $chunk) {
            $rows = DB::table('orders')
                ->whereNull('deleted_at')
                ->whereNotIn('status', self::EXCLUDED_STATUSES)
                ->whereIn('user_id', $chunk->all())
                ->groupBy('user_id')
                ->selectRaw('user_id, MIN(created_at) as first_at')
                ->get();

            foreach ($rows as $row) {
                $first[(int) $row->user_id] = (string) $row->first_at;
            }
        }

        return $first;
    }

    /**
     * Whole-window figures. Customers are counted once here, however many
     * periods they ordered in, which summing the table rows would not do.
     *
     * @param  Collection<int, array{user_id: int|null, revenue: float}>  $orders
     * @param  array<int, string>  $firstOrderAt
     * @return array<string, mixed>
     */
    private function totals(Collection $orders, array $firstOrderAt, ReportPeriod $period): array
    {
        $customers = $orders->pluck('user_id')->filter()->unique();
        $new = $customers->filter(
            fn (int $id): bool => isset($firstOrderAt[$id]) && $firstOrderAt[$id] >= $period->startUtc(),
        )->count();

        return [
            'orders' => $orders->count(),
            'unique_customers' => $customers->count(),
            'new_customers' => $new,
            'returning_customers' => $customers->count() - $new,
            'revenue' => round((float) $orders->sum('revenue'), 2),
        ];
    }
}
