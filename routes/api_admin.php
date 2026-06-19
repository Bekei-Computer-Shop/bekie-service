<?php

use App\Http\Controllers\Api\Admin\V1\AuthController;
use App\Http\Controllers\Api\Admin\V1\ProductController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public Admin Auth
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Protected Admin Routes
    Route::middleware([AuthenticateApiToken::class])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Product Management
        Route::apiResource('products', ProductController::class);
        Route::patch('products/{product}/status', [ProductController::class, 'changeStatus']);

        // Bulk Actions (Example of REST extension)
        Route::prefix('products')->group(function () {
            Route::post('bulk-status', [ProductController::class, 'bulkUpdateStatus']);
            Route::post('bulk-delete', [ProductController::class, 'bulkDestroy']);
        });
    });
});
