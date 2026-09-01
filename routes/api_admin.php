<?php

use App\Http\Controllers\Api\Admin\V1\ActivityLogController;
use App\Http\Controllers\Api\Admin\V1\AdministratorController;
use App\Http\Controllers\Api\Admin\V1\AuthController;
use App\Http\Controllers\Api\Admin\V1\BannerController;
use App\Http\Controllers\Api\Admin\V1\BrandController;
use App\Http\Controllers\Api\Admin\V1\CategoryController;
use App\Http\Controllers\Api\Admin\V1\ContentController;
use App\Http\Controllers\Api\Admin\V1\CustomerController;
use App\Http\Controllers\Api\Admin\V1\DashboardController;
use App\Http\Controllers\Api\Admin\V1\LogController;
use App\Http\Controllers\Api\Admin\V1\MediaController;
use App\Http\Controllers\Api\Admin\V1\NotificationController;
use App\Http\Controllers\Api\Admin\V1\OrderController;
use App\Http\Controllers\Api\Admin\V1\PermissionController;
use App\Http\Controllers\Api\Admin\V1\ProductController;
use App\Http\Controllers\Api\Admin\V1\PromotionController;
use App\Http\Controllers\Api\Admin\V1\ReportController;
use App\Http\Controllers\Api\Admin\V1\RoleController;
use App\Http\Controllers\Api\Admin\V1\StockController;
use App\Http\Controllers\Api\Admin\V1\UserController;
use App\Http\Middleware\AuthenticateAdminApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public Admin Auth
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-admin');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:auth-admin');

    // Protected Admin Routes
    Route::middleware([AuthenticateAdminApiToken::class])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->middleware('permission:admin.profile.view');
        Route::match(['put', 'patch'], 'auth/profile', [AuthController::class, 'updateProfile'])->middleware('permission:admin.profile.update');
        Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('permission:admin.auth.logout');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])
            ->middleware(['permission:admin.profile.update', 'throttle:auth-admin-sensitive']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);

        // Dashboard — the portal's landing page, so every admin role is granted
        // dashboard.view rather than it being scoped to a single capability.
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

        // Catalog Management
        Route::apiResource('brands', BrandController::class)->only(['index', 'show'])->middleware('permission:brands.view');
        Route::apiResource('brands', BrandController::class)->only('store')->middleware('permission:brands.create');
        Route::apiResource('brands', BrandController::class)->only(['update'])->middleware('permission:brands.update');
        Route::apiResource('brands', BrandController::class)->only('destroy')->middleware('permission:brands.delete');
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show'])->middleware('permission:categories.view');
        Route::apiResource('categories', CategoryController::class)->only('store')->middleware('permission:categories.create');
        Route::apiResource('categories', CategoryController::class)->only(['update'])->middleware('permission:categories.update');
        Route::apiResource('categories', CategoryController::class)->only('destroy')->middleware('permission:categories.delete');

        // Media Management
        Route::get('media', [MediaController::class, 'index'])->middleware('permission:media.view')->name('admin.media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('permission:media.create')->name('admin.media.store');
        Route::delete('media', [MediaController::class, 'destroy'])->middleware('permission:media.delete')->name('admin.media.destroy');

        // Product Management
        Route::apiResource('products', ProductController::class)->only(['index', 'show'])->middleware('permission:products.view');
        Route::apiResource('products', ProductController::class)->only('store')->middleware('permission:products.create');
        Route::apiResource('products', ProductController::class)->only(['update'])->middleware('permission:products.update');
        Route::apiResource('products', ProductController::class)->only('destroy')->middleware('permission:products.delete');
        Route::patch('products/{product}/status', [ProductController::class, 'changeStatus'])->middleware('permission:products.update');

        Route::prefix('products')->group(function () {
            Route::post('bulk-status', [ProductController::class, 'bulkUpdateStatus'])->middleware('permission:products.update');
            Route::post('bulk-delete', [ProductController::class, 'bulkDestroy'])->middleware('permission:products.delete');
        });

        Route::prefix('stock')->group(function () {
            Route::get('summary', [StockController::class, 'summary'])->middleware('permission:stock.view');
            Route::post('movements/bulk', [StockController::class, 'bulkStore'])->middleware(['permission:stock.update', 'throttle:admin-sensitive']);
            Route::get('export', [StockController::class, 'export'])->middleware(['permission:stock.view', 'throttle:admin-reports']);
            Route::get('movements/export', [StockController::class, 'movementExport'])->middleware(['permission:stock.view', 'throttle:admin-reports']);
            Route::get('alerts', [StockController::class, 'alerts'])->middleware('permission:stock.view');
            Route::get('movements', [StockController::class, 'movements'])->middleware('permission:stock.view');
            Route::post('movements', [StockController::class, 'store'])->middleware(['permission:stock.update', 'throttle:admin-sensitive']);
            Route::get('{id}', [StockController::class, 'show'])->middleware('permission:stock.view');
            Route::get('/', [StockController::class, 'index'])->middleware('permission:stock.view');
        });

        Route::middleware('permission:promotions.view')->group(function () {
            Route::get('promotions', [PromotionController::class, 'index']);
            Route::get('promotions/{promotion}', [PromotionController::class, 'show']);
        });
        Route::middleware('permission:promotions.create')->group(function () {
            Route::post('promotions', [PromotionController::class, 'store']);
        });
        Route::middleware('permission:promotions.update')->group(function () {
            Route::match(['put', 'patch'], 'promotions/{promotion}', [PromotionController::class, 'update']);
        });
        Route::middleware('permission:promotions.delete')->group(function () {
            Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy']);
        });

        Route::middleware('permission:content.view')->group(function () {
            Route::get('content', [ContentController::class, 'index']);
            Route::get('content/{item}', [ContentController::class, 'show']);
        });
        Route::middleware('permission:content.create')->group(function () {
            Route::post('content', [ContentController::class, 'store']);
        });
        Route::middleware('permission:content.update')->group(function () {
            Route::match(['put', 'patch'], 'content/{item}', [ContentController::class, 'update']);
            Route::post('content/{item}/publish', [ContentController::class, 'publish']);
            Route::post('content/{item}/archive', [ContentController::class, 'archive']);
        });
        Route::middleware('permission:content.delete')->group(function () {
            Route::delete('content/{item}', [ContentController::class, 'destroy']);
        });

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{user}', [CustomerController::class, 'show']);
        });
        Route::middleware('permission:customers.create')->group(function () {
            Route::post('customers', [CustomerController::class, 'store']);
        });
        Route::middleware('permission:customers.update')->group(function () {
            Route::match(['put', 'patch'], 'customers/{user}', [CustomerController::class, 'update']);
        });
        Route::middleware('permission:customers.delete')->group(function () {
            Route::delete('customers/{user}', [CustomerController::class, 'destroy']);
        });

        Route::middleware('permission:orders.view')->group(function () {
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show']);
        });
        Route::middleware('permission:orders.create')->group(function () {
            Route::post('orders', [OrderController::class, 'store']);
        });
        // orders.approve is allowed in here too: approving an order means
        // moving it through its statuses, so an approver may PATCH `status`.
        // UpdateOrderRequest prohibits payment_status and notes for a caller
        // that holds only orders.approve — those stay with orders.update.
        Route::middleware('permission:orders.update|orders.approve')->group(function () {
            Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update']);
        });
        // Approval is separate from editing: a manager may approve and reject
        // orders without being able to change or create them.
        Route::middleware('permission:orders.approve')->group(function () {
            Route::post('orders/{order}/approve', [OrderController::class, 'approve']);
            Route::post('orders/{order}/reject', [OrderController::class, 'reject']);
        });
        Route::middleware('permission:orders.delete')->group(function () {
            Route::delete('orders/{order}', [OrderController::class, 'destroy']);
        });

        Route::middleware('permission:administrators.view')->group(function () {
            Route::get('administrators', [AdministratorController::class, 'index']);
            Route::get('administrators/{user}', [AdministratorController::class, 'show']);
        });
        Route::middleware('permission:administrators.create')->group(function () {
            Route::post('administrators', [AdministratorController::class, 'store']);
        });
        Route::middleware('permission:administrators.update')->group(function () {
            Route::match(['put', 'patch'], 'administrators/{user}', [AdministratorController::class, 'update']);
        });
        Route::middleware('permission:administrators.delete')->group(function () {
            Route::delete('administrators/{user}', [AdministratorController::class, 'destroy']);
        });

        Route::middleware('permission:banners.view')->group(function () {
            Route::get('banners', [BannerController::class, 'index']);
            Route::get('banners/{banner}', [BannerController::class, 'show']);
        });
        Route::middleware('permission:banners.create')->group(function () {
            Route::post('banners', [BannerController::class, 'store']);
        });
        Route::middleware('permission:banners.update')->group(function () {
            Route::match(['put', 'patch'], 'banners/{banner}', [BannerController::class, 'update']);
            Route::post('banners/{banner}/status', [BannerController::class, 'toggleStatus']);
        });
        Route::middleware('permission:banners.delete')->group(function () {
            Route::delete('banners/{banner}', [BannerController::class, 'destroy']);
        });

        Route::middleware(['permission:logs.view', 'throttle:admin-reports'])->group(function () {
            Route::get('logs/visitors', [LogController::class, 'visitors']);
            Route::get('logs/team', [LogController::class, 'team']);
            Route::get('activity-logs', [ActivityLogController::class, 'index']);
            Route::get('reports/sold-products', [ReportController::class, 'soldProducts']);
            Route::get('reports/customer-orders', [ReportController::class, 'customerOrders']);
        });

        // ─── User management (admin/staff CRUD + role assignment) ────
        Route::middleware('permission:users.view')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('admin.users.show');
        });
        Route::middleware('permission:users.create')->group(function () {
            Route::post('users', [UserController::class, 'store'])->name('admin.users.store');
        });
        Route::middleware('permission:users.update')->group(function () {
            Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::post('users/{user}/roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign-role')->name('admin.users.assign-roles');
            Route::delete('users/{user}/roles/{role}', [UserController::class, 'revokeRole'])->middleware('permission:users.assign-role')->name('admin.users.revoke-role');
        });
        Route::middleware('permission:users.delete')->group(function () {
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
            Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('admin.users.restore');
        });

        // ─── Role management (CRUD + permission sync) ─────────────────
        Route::middleware('permission:roles.view')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('admin.roles.index');
            Route::get('roles/{role}', [RoleController::class, 'show'])->name('admin.roles.show');
        });
        Route::middleware('permission:roles.create')->group(function () {
            Route::post('roles', [RoleController::class, 'store'])->name('admin.roles.store');
        });
        Route::middleware('permission:roles.update')->group(function () {
            Route::patch('roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.assign-permission')->name('admin.roles.sync-permissions');
        });
        Route::middleware('permission:roles.delete')->group(function () {
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
        });

        // ─── Permission management (CRUD) ─────────────────────────────
        Route::middleware('permission:permissions.view')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index'])->name('admin.permissions.index');
            Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('admin.permissions.show');
        });
        Route::middleware('permission:permissions.create')->group(function () {
            Route::post('permissions', [PermissionController::class, 'store'])->name('admin.permissions.store');
        });
        Route::middleware('permission:permissions.update')->group(function () {
            Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->name('admin.permissions.update');
            Route::put('permissions/{permission}', [PermissionController::class, 'update']);
        });
        Route::middleware('permission:permissions.delete')->group(function () {
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('admin.permissions.destroy');
        });
    });
});
