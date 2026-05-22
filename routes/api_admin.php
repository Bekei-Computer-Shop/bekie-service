<?php

use App\Http\Controllers\Api\Admin\V1\AuthController;
use App\Http\Controllers\Api\Admin\V1\DashboardController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AdminRoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware([AuthenticateApiToken::class, AdminRoleMiddleware::class])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Future admin routes for the panel should be placed here.
        // Example:
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('orders', OrderController::class);
        // Route::apiResource('customers', CustomerController::class);
    });
});
