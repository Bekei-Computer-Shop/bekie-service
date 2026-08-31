<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseAdminController
{
    /**
     * The order statuses the admin portal charts, in the order its donut and
     * legend expect them. A status outside this list (a refund, say) still
     * counts toward `total_orders` but has no segment, because the portal has
     * no label or colour for it.
     *
     * @var list<string>
     */
    private const CHARTED_STATUSES = ['pending', 'processing', 'completed', 'cancelled'];

    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $thisYear = Carbon::now()->startOfYear();

        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => Order::sum('grand_total'),
            'total_products' => Product::count(),
            'total_customers' => User::where('is_admin', false)->count(),
            'total_promotions' => Promotion::count(),
            'today' => [
                'orders' => Order::whereDate('created_at', $today)->count(),
                'revenue' => Order::whereDate('created_at', $today)->sum('grand_total'),
            ],
            'this_month' => [
                'orders' => Order::whereBetween('created_at', [$thisMonth, now()])->count(),
                'revenue' => Order::whereBetween('created_at', [$thisMonth, now()])->sum('grand_total'),
            ],
            'this_year' => [
                'orders' => Order::whereBetween('created_at', [$thisYear, now()])->count(),
                'revenue' => Order::whereBetween('created_at', [$thisYear, now()])->sum('grand_total'),
            ],
            'orders_per_day' => $this->ordersPerDay(),
            'status_breakdown' => $this->statusBreakdown(),
            'recent_orders' => $this->recentOrders(),
        ];

        return $this->success($stats, 'Admin dashboard statistics retrieved.');
    }

    /**
     * Orders created on each day of the current week, Mon -> Sun.
     *
     * Always seven entries: the portal's bar chart keeps its columns in place
     * during a quiet week rather than collapsing to the days that happen to
     * have orders.
     *
     * The bucketing is done in PHP over a single ranged query rather than with
     * a grouped `DATE(created_at)`, because that function differs between the
     * SQLite the suite runs on and the Postgres production uses. A week of
     * orders is a small enough set to group in memory.
     *
     * @return list<array{date: string, day: string, orders: int}>
     */
    private function ordersPerDay(): array
    {
        $weekStart = Carbon::now()->startOfWeek();

        $counts = Order::query()
            ->whereBetween('created_at', [$weekStart, Carbon::now()->endOfWeek()])
            ->pluck('created_at')
            ->countBy(fn (Carbon $createdAt): string => $createdAt->toDateString());

        return collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $counts): array {
                $day = $weekStart->copy()->addDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'day' => $day->format('D'),
                    'orders' => (int) $counts->get($day->toDateString(), 0),
                ];
            })
            ->all();
    }

    /**
     * Order counts per charted status, zero-filled so the donut's legend always
     * lists all four states.
     *
     * @return list<array{status: string, count: int}>
     */
    private function statusBreakdown(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(self::CHARTED_STATUSES)
            ->map(fn (string $status): array => [
                'status' => $status,
                'count' => (int) $counts->get($status, 0),
            ])
            ->all();
    }

    /**
     * The five newest orders, flattened into the columns the portal's table
     * renders so it needs no follow-up requests.
     *
     * `id` is the uuid rather than the primary key, because that is what the
     * portal's order-detail route resolves on (see Order::getRouteKeyName).
     *
     * @return list<array{id: string, order_number: string, customer: string, item: string, amount: string, status: string}>
     */
    private function recentOrders(): array
    {
        // `user` is loaded whole rather than with a column list: the customer
        // name comes from a `name` accessor over first_name/last_name, so a
        // column list naming it would fail on Postgres.
        return Order::query()
            ->with(['user', 'items'])
            ->orderByDesc('created_at')
            // Orders placed in the same second would otherwise come back in
            // whatever order the engine chose, so the panel could reshuffle
            // between refreshes.
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                // The snapshot is the name as it stood when the order was
                // placed, which survives the customer renaming or deleting
                // their account; the relation is only the fallback.
                'customer' => $order->customer_snapshot['name']
                    ?? $order->user?->name
                    ?? 'Guest',
                'item' => $this->itemSummary($order),
                'amount' => $order->grand_total,
                'status' => $order->status,
            ])
            ->all();
    }

    /**
     * One cell's worth of an order's contents: the first line's product, plus a
     * count of the rest so a five-line order does not read as a one-line one.
     */
    private function itemSummary(Order $order): string
    {
        $first = $order->items->first();

        if (! $first) {
            return '—';
        }

        $others = $order->items->count() - 1;

        return $others > 0
            ? $first->product_name.' +'.$others.' more'
            : (string) $first->product_name;
    }
}
