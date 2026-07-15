<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\DashboardResource;
use App\Models\ReportsCache;
use App\Models\TeamActivityLog;
use App\Models\User;
use App\Models\VisitorLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseAdminController
{
    public function overview(Request $request): \Illuminate\Http\JsonResponse
    {
        $today = today();
        $yesterday = today()->subDay();

        $pendingOrders = Order::where('status', 'pending')->count();
        $newCustomers = User::role('customer')->whereDate('created_at', $today)->count();
        $revenueToday = Order::whereDate('created_at', $today)
            ->whereNotIn('status', ['rejected'])
            ->sum('grand_total');
        $approvalQueue = $pendingOrders;

        $dailyReports = [
            'hardware_sales' => $this->buildTrend('hardware_sales', $today, $yesterday),
            'peripheral_sales' => $this->buildTrend('peripheral_sales', $today, $yesterday),
            'unique_visitors' => $this->buildTrend('unique_visitors', $today, $yesterday),
        ];

        $salesByCategory = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereDate('orders.created_at', $today)
            ->whereNotIn('orders.status', ['rejected'])
            ->selectRaw('categories.name, SUM(order_items.quantity * order_items.unit_price) as revenue')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(4)
            ->get();

        $totalRevenue = $salesByCategory->sum('revenue') ?: 1;

        $salesByCategory = $salesByCategory->map(fn ($row) => [
            'category' => $row->name,
            'revenue' => round((float) $row->revenue, 2),
            'share_pct' => round(((float) $row->revenue / $totalRevenue) * 100, 2),
        ]);

        $visitorLog = VisitorLog::latest('created_at')->limit(20)->get();
        $teamActivity = TeamActivityLog::with('actor')->latest('created_at')->limit(10)->get();

        return $this->success(new DashboardResource([
            'kpis' => [
                'pending_orders' => $pendingOrders,
                'new_customers_today' => $newCustomers,
                'revenue_today' => number_format($revenueToday, 2, '.', ''),
                'approval_queue' => $approvalQueue,
            ],
            'daily_reports' => $dailyReports,
            'sales_by_category' => $salesByCategory,
            'visitor_log' => $visitorLog,
            'team_activity' => $teamActivity,
        ]));
    }

    public function reports(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'granularity' => ['sometimes', 'in:daily,weekly,monthly,yearly'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $granularity = $validated['granularity'] ?? 'daily';
        $from = isset($validated['date_from']) ? $validated['date_from'] : today()->subDays(6)->toDateString();
        $to = isset($validated['date_to']) ? $validated['date_to'] : today()->toDateString();

        $records = ReportsCache::whereBetween('report_date', [$from, $to])
            ->where('granularity', $granularity)
            ->orderBy('report_date')
            ->get()
            ->groupBy('metric_key')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'date' => $row->report_date->toDateString(),
                'value' => (float) $row->metric_value,
            ])->values())
            ->toArray();

        return $this->success($records);
    }

    private function buildTrend(string $metricKey, \Illuminate\Support\Carbon $today, \Illuminate\Support\Carbon $yesterday): array
    {
        $todayValue = ReportsCache::forDate($today->toDateString())
            ->granularity('daily')
            ->where('metric_key', $metricKey)
            ->value('metric_value');

        $yesterdayValue = ReportsCache::forDate($yesterday->toDateString())
            ->granularity('daily')
            ->where('metric_key', $metricKey)
            ->value('metric_value');

        $todayValue = $todayValue ? (float) $todayValue : 0.0;
        $yesterdayValue = $yesterdayValue ? (float) $yesterdayValue : 0.0;

        $change = $yesterdayValue === 0 ? 0.0 : round((($todayValue - $yesterdayValue) / max($yesterdayValue, 1)) * 100, 1);
        $direction = $todayValue > $yesterdayValue ? 'up' : ($todayValue < $yesterdayValue ? 'down' : 'flat');

        return [
            'value' => round($todayValue, 2),
            'change_pct' => $change,
            'direction' => $direction,
        ];
    }
}
