<?php

use App\Http\Controllers\Api\Admin\AdministratorController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PromotionController;
use App\Http\Controllers\Api\Admin\V1\AuthController;
use App\Http\Controllers\Api\Admin\V1\BrandController;
use App\Http\Controllers\Api\Admin\V1\CategoryController;
use App\Http\Controllers\Api\Admin\V1\MediaController;
use App\Http\Controllers\Api\Admin\V1\PermissionController;
use App\Http\Controllers\Api\Admin\V1\ProductController;
use App\Http\Controllers\Api\Admin\V1\RoleController;
use App\Http\Controllers\Api\Admin\V1\StockController;
use App\Http\Controllers\Api\Admin\V1\UserController;
use App\Http\Middleware\AuthenticateAdminApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public Admin Auth
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Protected Admin Routes
    Route::middleware([AuthenticateAdminApiToken::class])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Catalog Management
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('categories', CategoryController::class);

        // Media Management
        Route::get('media', [MediaController::class, 'index'])->name('admin.media.index');
        Route::post('media', [MediaController::class, 'store'])->name('admin.media.store');
        Route::delete('media', [MediaController::class, 'destroy'])->name('admin.media.destroy');

        // Product Management
        Route::apiResource('products', ProductController::class);
        Route::patch('products/{product}/status', [ProductController::class, 'changeStatus']);

        // Bulk Actions (Example of REST extension)
        Route::prefix('products')->group(function () {
            Route::post('bulk-status', [ProductController::class, 'bulkUpdateStatus']);
            Route::post('bulk-delete', [ProductController::class, 'bulkDestroy']);
        });

        Route::prefix('stock')->group(function () {
            Route::get('alerts', [StockController::class, 'alerts']);
            Route::get('movements', [StockController::class, 'movements']);
            Route::post('movements', [StockController::class, 'store']);
            Route::get('/', [StockController::class, 'index']);
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

        Route::middleware('permission:orders.view')->group(function () {
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show']);
        });
        Route::middleware('permission:orders.create')->group(function () {
            Route::post('orders', [OrderController::class, 'store']);
        });
        Route::middleware('permission:orders.update')->group(function () {
            Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update']);
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

        Route::middleware('permission:logs.view')->group(function () {
            Route::get('logs/visitors', [LogController::class, 'visitors']);
            Route::get('logs/team', [LogController::class, 'team']);
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
            Route::post('users/{user}/roles', [UserController::class, 'assignRoles'])->name('admin.users.assign-roles');
            Route::delete('users/{user}/roles/{role}', [UserController::class, 'revokeRole'])->name('admin.users.revoke-role');
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
            Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('admin.roles.sync-permissions');
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
