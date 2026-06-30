<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Middleware\EnsureAdminWebAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentation is served by Scramble at /docs/client and /docs/admin.
// The legacy /api/docs* paths are preserved as 301 redirects so existing
// bookmarks continue to work after the migration.
Route::redirect('/api/docs', '/docs/client', 301);
Route::redirect('/api/admin/docs', '/docs/admin', 301);
Route::redirect('/api/docs/redoc', '/docs/client', 301);
Route::redirect('/api/admin/docs/redoc', '/docs/admin', 301);

Route::middleware(['guest:web'])->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.submit');
});

Route::prefix('admin')
    ->middleware([EnsureAdminWebAccess::class])
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'overview'])->name('dashboard');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('administrators', [AdministratorController::class, 'index'])->name('administrators.index');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
        Route::resource('promotions', PromotionController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);

        Route::get('content/{type}', [ContentController::class, 'index'])->name('content.index');
        Route::get('content/{type}/create', [ContentController::class, 'create'])->name('content.create');
        Route::post('content/{type}', [ContentController::class, 'store'])->name('content.store');
        Route::get('content/{type}/{contentItem}/edit', [ContentController::class, 'edit'])->name('content.edit');
        Route::put('content/{type}/{contentItem}', [ContentController::class, 'update'])->name('content.update');
        Route::delete('content/{type}/{contentItem}', [ContentController::class, 'destroy'])->name('content.destroy');

        Route::get('logs/visitors', [LogController::class, 'visitorLogs'])->name('logs.visitors');
        Route::get('logs/activities', [LogController::class, 'activityLogs'])->name('logs.activities');

        Route::post('reports/sales/export', [ReportExportController::class, 'exportSales'])->name('reports.sales.export');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
