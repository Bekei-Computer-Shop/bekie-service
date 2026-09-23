<?php

use App\Http\Controllers\Api\Client\V1\AbaPayWayController;
use App\Http\Controllers\Api\Client\V1\AuthController;
use App\Http\Controllers\Api\Client\V1\BrandController;
use App\Http\Controllers\Api\Client\V1\CartController;
use App\Http\Controllers\Api\Client\V1\CategoryController;
use App\Http\Controllers\Api\Client\V1\ContactController;
use App\Http\Controllers\Api\Client\V1\ContentPageController;
use App\Http\Controllers\Api\Client\V1\CouponController;
use App\Http\Controllers\Api\Client\V1\EmailVerificationController;
use App\Http\Controllers\Api\Client\V1\KhqrController;
use App\Http\Controllers\Api\Client\V1\MasterDataController;
use App\Http\Controllers\Api\Client\V1\MyProductController;
use App\Http\Controllers\Api\Client\V1\OrderController;
use App\Http\Controllers\Api\Client\V1\ProductController;
use App\Http\Controllers\Api\Client\V1\ProfileController;
use App\Http\Controllers\Api\Client\V1\PromotionController;
use App\Http\Controllers\Api\Client\V1\ReviewController;
use App\Http\Controllers\Api\Client\V1\ShippingMethodController;
use App\Http\Controllers\Api\Client\V1\SlideController;
use App\Http\Controllers\Api\Client\V1\UserProfileController;
use App\Http\Controllers\Api\Client\V1\WarrantyController;
use App\Http\Controllers\Api\Client\V1\WishlistController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateAdminApiToken;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes([
    'prefix' => 'v1',
    'middleware' => [AuthenticateAdminApiToken::class],
]);

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
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])
        ->middleware(AuthenticateApiToken::class);
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact');
    Route::post('auth/verify-email-otp', [EmailVerificationController::class, 'verify'])->middleware('throttle:auth-otp');
    Route::post('auth/resend-email-otp', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth-otp');

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

    // Payment options (public endpoints)
    Route::get('payments/options', [AbaPayWayController::class, 'options']);
    Route::get('payments/khqr/options', [KhqrController::class, 'options']);
    Route::post('payments/aba/callback', [AbaPayWayController::class, 'callback']);

    // Storefront content: homepage carousel slides and live promotions.
    Route::get('slides', [SlideController::class, 'index']);
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::get('about-us', [ContentPageController::class, 'show'])->defaults('slug', 'about-us');
    Route::get('terms-and-conditions', [ContentPageController::class, 'show'])->defaults('slug', 'terms-and-conditions');
    Route::get('contact-us', [ContentPageController::class, 'show'])->defaults('slug', 'contact-us');

    Route::middleware(AuthenticateApiToken::class)->group(function () {
        Route::get('profile', [UserProfileController::class, 'show']);
        Route::get('my-products/summary', [MyProductController::class, 'summary']);
        Route::get('my-products', [MyProductController::class, 'index']);
        Route::get('my-products/{productSerial}', [MyProductController::class, 'show']);
        Route::get('warranty/summary', [WarrantyController::class, 'summary']);
        Route::get('warranty/{serialNumber}', [WarrantyController::class, 'validate']);
        Route::post('profile/avatar', [UserProfileController::class, 'updateAvatar']);

        Route::post('coupons/apply', [CouponController::class, 'apply'])
            ->middleware(['permission:client.coupons.apply', 'throttle:promo-apply']);

        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::patch('/', [ProfileController::class, 'update'])->middleware('permission:client.profile.update');
            Route::post('change-password', [ProfileController::class, 'changePassword'])->middleware('permission:client.profile.change-password');
            Route::post('image', [ProfileController::class, 'updateProfileImage'])->middleware('permission:client.profile.update-image');
        });

        Route::prefix('reviews')->middleware('permission:client.reviews.manage')->group(function () {
            Route::get('/', [ReviewController::class, 'index']);
            Route::post('/', [ReviewController::class, 'store']);
            Route::get('{review}', [ReviewController::class, 'show']);
            Route::patch('{review}', [ReviewController::class, 'update']);
            Route::delete('{review}', [ReviewController::class, 'destroy']);
        });

        Route::prefix('cart')->middleware('permission:client.carts.manage')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::put('/', [CartController::class, 'store']);
            Route::post('items', [CartController::class, 'addItem']);
            Route::patch('items/{item}', [CartController::class, 'updateItem']);
            Route::delete('items/{item}', [CartController::class, 'removeItem']);
            Route::post('checkout', [CartController::class, 'checkout'])->middleware('throttle:client-checkout');
        });

        Route::prefix('wishlists')->middleware('permission:client.wishlists.manage')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::put('/', [WishlistController::class, 'store']);
            Route::delete('/', [WishlistController::class, 'destroy']);
            Route::get('check', [WishlistController::class, 'checkProduct']);
            Route::post('items', [WishlistController::class, 'addItem']);
            Route::delete('items/{item}', [WishlistController::class, 'removeItem']);
        });

        Route::prefix('wishlist')->middleware('permission:client.wishlists.manage')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::put('/', [WishlistController::class, 'store']);
            Route::delete('/', [WishlistController::class, 'destroy']);
            Route::get('check', [WishlistController::class, 'checkProduct']);
            Route::post('items', [WishlistController::class, 'addItem']);
            Route::delete('items/{item}', [WishlistController::class, 'removeItem']);
        });

        Route::prefix('orders')->middleware('permission:client.orders.manage')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store'])->middleware('throttle:client-checkout');
            Route::get('{order}', [OrderController::class, 'show']);
        });

        Route::middleware('permission:client.orders.manage')->group(function () {
            // ABA PayWay payment endpoints
            Route::post('payments/aba/purchase', [AbaPayWayController::class, 'purchase'])->middleware('throttle:client-checkout');
            Route::get('payments/aba/{transactionId}', [AbaPayWayController::class, 'check'])->middleware('throttle:client-checkout');

            // KHQR payment endpoints
            Route::post('payments/khqr/generate', [KhqrController::class, 'generate'])->middleware('throttle:client-checkout');
            Route::get('payments/khqr/{transactionId}', [KhqrController::class, 'check'])->middleware('throttle:client-checkout');
            Route::post('payments/khqr/{transactionId}/confirm', [KhqrController::class, 'confirm'])->middleware('throttle:client-checkout');
            Route::delete('payments/khqr/{transactionId}', [KhqrController::class, 'cancel'])->middleware('throttle:client-checkout');
        });
    });

    require __DIR__.'/api_admin.php';
});
