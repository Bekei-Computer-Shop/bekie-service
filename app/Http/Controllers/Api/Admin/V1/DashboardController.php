<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $thisYear = Carbon::now()->startOfYear();

        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => Order::sum('total_amount'),
            'total_products' => Product::count(),
            'total_customers' => User::where('is_admin', false)->count(),
            'today' => [
                'orders' => Order::whereDate('created_at', $today)->count(),
                'revenue' => Order::whereDate('created_at', $today)->sum('total_amount'),
            ],
            'this_month' => [
                'orders' => Order::whereBetween('created_at', [$thisMonth, now()])->count(),
                'revenue' => Order::whereBetween('created_at', [$thisMonth, now()])->sum('total_amount'),
            ],
            'this_year' => [
                'orders' => Order::whereBetween('created_at', [$thisYear, now()])->count(),
                'revenue' => Order::whereBetween('created_at', [$thisYear, now()])->sum('total_amount'),
            ],
        ];

        return $this->success($stats, 'Admin dashboard statistics retrieved.');
    }
}
