<?php

use App\Http\Controllers\Api\Client\V1\AuthController;
use App\Http\Controllers\Api\Client\V1\BrandController;
use App\Http\Controllers\Api\Client\V1\CartController;
use App\Http\Controllers\Api\Client\V1\CategoryController;
use App\Http\Controllers\Api\Client\V1\CouponController;
use App\Http\Controllers\Api\Client\V1\MasterDataController;
use App\Http\Controllers\Api\Client\V1\OrderController;
use App\Http\Controllers\Api\Client\V1\ProductController;
use App\Http\Controllers\Api\Client\V1\PromotionController;
use App\Http\Controllers\Api\Client\V1\ShippingMethodController;
use App\Http\Controllers\Api\Client\V1\SlideController;
use App\Http\Controllers\Api\Client\V1\WishlistController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

// Public health check. Lives outside the /v1 surface so external monitors
// (Render, uptime robots) can hit a stable, unversioned URL.
Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-client');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-client');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:auth-client');
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware([AuthenticateApiToken::class, 'permission:client.auth.logout']);

    Route::prefix('master')->group(function () {
        Route::get('categories', [MasterDataController::class, 'categories']);
        Route::get('brands', [MasterDataController::class, 'brands']);
        Route::get('shipping-methods', [MasterDataController::class, 'shippingMethods']);
    });

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/variants', [ProductController::class, 'variants']);

    Route::get('shipping-methods', [ShippingMethodController::class, 'index']);

    // Storefront content: homepage carousel slides and live promotions.
    Route::get('slides', [SlideController::class, 'index']);
    Route::get('promotions', [PromotionController::class, 'index']);

    Route::middleware(AuthenticateApiToken::class)->group(function () {
        Route::post('coupons/apply', [CouponController::class, 'apply'])
            ->middleware(['permission:client.coupons.apply', 'throttle:promo-apply']);

        Route::prefix('carts')->middleware('permission:client.carts.manage')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::get('{cart}', [CartController::class, 'show']);
            Route::post('{cart}/items', [CartController::class, 'addItem']);
            Route::patch('{cart}/items/{item}', [CartController::class, 'updateItem']);
            Route::delete('{cart}/items/{item}', [CartController::class, 'removeItem']);
            Route::post('{cart}/checkout', [CartController::class, 'checkout'])->middleware('throttle:client-checkout');
        });

        Route::prefix('wishlists')->middleware('permission:client.wishlists.manage')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::get('{wishlist}', [WishlistController::class, 'show']);
            Route::delete('{wishlist}', [WishlistController::class, 'destroy']);
            Route::post('{wishlist}/items', [WishlistController::class, 'addItem']);
            Route::delete('{wishlist}/items/{item}', [WishlistController::class, 'removeItem']);
        });

        Route::prefix('orders')->middleware('permission:client.orders.manage')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store'])->middleware('throttle:client-checkout');
            Route::get('{order}', [OrderController::class, 'show']);
        });
    });

    require __DIR__.'/api_admin.php';
});
