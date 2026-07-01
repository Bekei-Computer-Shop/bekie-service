<?php

use App\Http\Controllers\Api\Admin\V1\AuthController;
use App\Http\Controllers\Api\Admin\V1\BrandController;
use App\Http\Controllers\Api\Admin\V1\CategoryController;
use App\Http\Controllers\Api\Admin\V1\PermissionController;
use App\Http\Controllers\Api\Admin\V1\ProductController;
use App\Http\Controllers\Api\Admin\V1\RoleController;
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

        // Product Management
        Route::apiResource('products', ProductController::class);
        Route::patch('products/{product}/status', [ProductController::class, 'changeStatus']);

        // Bulk Actions (Example of REST extension)
        Route::prefix('products')->group(function () {
            Route::post('bulk-status', [ProductController::class, 'bulkUpdateStatus']);
            Route::post('bulk-delete', [ProductController::class, 'bulkDestroy']);
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
