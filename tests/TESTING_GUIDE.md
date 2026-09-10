# Client API Testing Guide

Comprehensive automated test suite for all Bekie Service client API endpoints.

## Overview

This document outlines the complete test coverage for the client API (routes prefixed with `/api/v1`). The test suite is built using **Pest v3** with Laravel's testing utilities.

## Test Files Created

### 1. **AuthTest** - Authentication Endpoints
- **File**: `tests/Feature/Api/Client/V1/AuthTest.php`
- **Coverage**: 18 tests
- **Endpoints**:
  - `POST /api/v1/auth/register` - User registration
  - `POST /api/v1/auth/login` - User login
  - `POST /api/v1/auth/logout` - User logout
  - `POST /api/v1/auth/refresh` - Token refresh
  - `POST /api/v1/auth/change-password` - Change password

**Test Cases**:
- ✓ Register with email
- ✓ Register with phone
- ✓ Register requires email or phone
- ✓ Duplicate email/phone validation
- ✓ Password confirmation
- ✓ Login with valid/invalid credentials
- ✓ Logout revokes token
- ✓ Token refresh
- ✓ Change password with validation
- ✓ Authentication requirements

### 2. **ProfileTest** - User Profile Endpoints
- **File**: `tests/Feature/Api/Client/V1/ProfileTest.php`
- **Coverage**: 12 tests
- **Endpoints**:
  - `GET /api/v1/profile` - Get user profile
  - `POST /api/v1/profile/avatar` - Upload profile image

**Test Cases**:
- ✓ Get profile success
- ✓ Profile with/without avatar
- ✓ Upload image formats (JPG, PNG, GIF)
- ✓ Image size validation (max 5MB)
- ✓ Replace old avatar on new upload
- ✓ File type validation
- ✓ Authentication requirements

### 3. **ProductsTest** - Product Endpoints
- **File**: `tests/Feature/Api/Client/V1/ProductsTest.php`
- **Coverage**: 15 tests
- **Endpoints**:
  - `GET /api/v1/products` - List products
  - `GET /api/v1/products/{id}` - Get product detail
  - `GET /api/v1/products/{id}/variants` - Get product variants

**Test Cases**:
- ✓ List active products
- ✓ Filter by category
- ✓ Filter by brand
- ✓ Pagination
- ✓ Product detail with images and variants
- ✓ Exclude inactive products
- ✓ Exclude cost price from client response
- ✓ View counter increments
- ✓ UUID and ID resolution

### 4. **ProductVariantTest** - Product Variants
- **File**: `tests/Feature/Api/Client/V1/ProductVariantTest.php`
- **Coverage**: 4 tests
- **Endpoints**:
  - `GET /api/v1/products/{product}/variants` - Get variants

**Test Cases**:
- ✓ List product variants
- ✓ Exclude inactive variants
- ✓ 404 for inactive products
- ✓ 404 for non-existent products

### 5. **CategoryTest** - Category Endpoints
- **File**: `tests/Feature/Api/Client/V1/CategoryTest.php`
- **Coverage**: 6 tests
- **Endpoints**:
  - `GET /api/v1/categories` - List categories
  - `GET /api/v1/categories/{id}` - Get category detail

**Test Cases**:
- ✓ List active categories
- ✓ Exclude inactive categories
- ✓ Get category detail by ID/slug
- ✓ 404 for inactive categories

### 6. **BrandTest** - Brand Endpoints
- **File**: `tests/Feature/Api/Client/V1/BrandTest.php`
- **Coverage**: 6 tests
- **Endpoints**:
  - `GET /api/v1/brands` - List brands
  - `GET /api/v1/brands/{id}` - Get brand detail

**Test Cases**:
- ✓ List active brands
- ✓ Exclude inactive brands
- ✓ Get brand detail by ID/slug
- ✓ 404 for inactive brands

### 7. **OrderTest** - Order Endpoints
- **File**: `tests/Feature/Api/Client/V1/OrderTest.php`
- **Coverage**: 12 tests
- **Endpoints**:
  - `GET /api/v1/orders` - Get order history
  - `GET /api/v1/orders/{id}` - Get order detail
  - `POST /api/v1/orders` - Create order from cart

**Test Cases**:
- ✓ List user orders with pagination
- ✓ Order detail with items
- ✓ Only user's own orders visible
- ✓ Ordered by most recent
- ✓ Create order from cart
- ✓ Validate cart items required
- ✓ Access control (forbid other users' carts)
- ✓ Authentication requirements

### 8. **CartTest** - Shopping Cart Endpoints
- **File**: `tests/Feature/Api/Client/V1/CartTest.php`
- **Coverage**: 12 tests
- **Endpoints**:
  - `GET /api/v1/cart` - Get cart
  - `PUT /api/v1/cart` - Create/update cart
  - `POST /api/v1/cart/items` - Add item
  - `PATCH /api/v1/cart/items/{item}` - Update item
  - `DELETE /api/v1/cart/items/{item}` - Remove item

**Test Cases**:
- ✓ Get cart with items
- ✓ Create/update cart
- ✓ Add item to cart
- ✓ Update item quantity
- ✓ Remove item from cart
- ✓ Validate product exists
- ✓ Validate quantity
- ✓ Authentication requirements

### 9. **WishlistTest** - Wishlist Endpoints
- **File**: `tests/Feature/Api/Client/V1/WishlistTest.php`
- **Coverage**: 11 tests
- **Endpoints**:
  - `GET /api/v1/wishlist` - Get wishlist
  - `PUT /api/v1/wishlist` - Create/update wishlist
  - `POST /api/v1/wishlist/items` - Add item
  - `DELETE /api/v1/wishlist/items/{item}` - Remove item
  - `GET /api/v1/wishlist/check` - Check product in wishlist
  - `DELETE /api/v1/wishlist` - Delete wishlist

**Test Cases**:
- ✓ List wishlist items
- ✓ Create/update wishlist
- ✓ Add/remove items
- ✓ Check product in wishlist
- ✓ Delete wishlist
- ✓ Validate product exists
- ✓ Authentication requirements

### 10. **CouponTest** - Coupon/Promotion Endpoints
- **File**: `tests/Feature/Api/Client/V1/CouponTest.php`
- **Coverage**: 9 tests
- **Endpoints**:
  - `POST /api/v1/coupons/apply` - Apply coupon to cart

**Test Cases**:
- ✓ Apply percentage discount
- ✓ Apply fixed discount
- ✓ Validate minimum order amount
- ✓ Validate coupon exists
- ✓ Validate coupon not expired
- ✓ Validate coupon active
- ✓ Validate coupon not exhausted
- ✓ Authentication requirements

### 11. **ShippingMethodTest** - Shipping Endpoints
- **File**: `tests/Feature/Api/Client/V1/ShippingMethodTest.php`
- **Coverage**: 4 tests
- **Endpoints**:
  - `GET /api/v1/shipping-methods` - List shipping methods
  - `GET /api/v1/master/shipping-methods` - Master data shipping

**Test Cases**:
- ✓ List active shipping methods
- ✓ Exclude inactive methods
- ✓ Sort by sort_order

### 12. **MasterDataTest** - Master Data Endpoints
- **File**: `tests/Feature/Api/Client/V1/MasterDataTest.php`
- **Coverage**: 6 tests
- **Endpoints**:
  - `GET /api/v1/master/categories` - Master categories
  - `GET /api/v1/master/brands` - Master brands
  - `GET /api/v1/master/shipping-methods` - Master shipping

**Test Cases**:
- ✓ Lightweight master data endpoints
- ✓ Exclude inactive items
- ✓ Minimal payload structure

## Running Tests

### Run All Client API Tests
```bash
php artisan test tests/Feature/Api/Client/V1 --compact
```

### Run Specific Test Class
```bash
php artisan test --filter=AuthTest --compact
```

### Run Single Test Method
```bash
php artisan test --filter=test_register_with_email --compact
```

### Run with Coverage Report
```bash
php artisan test --coverage tests/Feature/Api/Client/V1
```

### Run Tests in Parallel
```bash
php artisan test tests/Feature/Api/Client/V1 --parallel
```

## Test Structure

Each test file follows this pattern:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YourTest extends TestCase
{
    use RefreshDatabase;  // Fresh database for each test

    protected function setUp(): void
    {
        parent::setUp();
        // Setup test data
    }

    public function test_your_feature(): void
    {
        $response = $this->getJson('/api/v1/endpoint');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([...]);
    }
}
```

## Test Assertions

Common assertions used:

```php
$response->assertOk();                    // 200
$response->assertCreated();               // 201
$response->assertUnauthorized();          // 401
$response->assertForbidden();             // 403
$response->assertNotFound();              // 404
$response->assertUnprocessable();         // 422

$response->assertJsonPath('data.id', 1);
$response->assertJsonStructure(['status', 'data']);
$response->assertJsonCount(3, 'data');
$response->assertJsonMissingPath('data.password');
```

## Test Coverage Summary

| Feature | Tests | Status |
|---------|-------|--------|
| Authentication | 18 | ✓ Comprehensive |
| User Profile | 12 | ✓ Comprehensive |
| Products | 15 | ✓ Comprehensive |
| Categories | 6 | ✓ Comprehensive |
| Brands | 6 | ✓ Comprehensive |
| Orders | 12 | ✓ Comprehensive |
| Cart | 12 | ✓ Comprehensive |
| Wishlist | 11 | ✓ Comprehensive |
| Coupons | 9 | ✓ Comprehensive |
| Shipping | 4 | ✓ Basic |
| Master Data | 6 | ✓ Comprehensive |
| **Total** | **111** | **✓** |

## Key Testing Patterns

### 1. Authentication Testing
All protected endpoints use `$this->withHeader('Authorization', "Bearer {$token->token}")`

### 2. Database State
Uses `RefreshDatabase` trait to isolate tests and prevent data pollution

### 3. Factory Usage
Tests use model factories for creating realistic test data:
```php
$user = User::factory()->create();
$product = Product::factory()->create(['is_active' => true]);
```

### 4. Response Validation
All responses verify both status code and JSON structure

### 5. Validation Testing
Tests cover both success and failure paths for input validation

## Notes

- Tests are database-agnostic and run against SQLite in-memory database during test execution
- All sensitive data (passwords, tokens) is properly tested but never logged
- Tests follow Laravel 12 conventions and Pest v3 syntax
- Each test is independent and can run in any order
- Factory models automatically create related records as needed

## Extending Tests

To add tests for new endpoints:

1. Create a new test class in `tests/Feature/Api/Client/V1/`
2. Extend `Tests\TestCase`
3. Use `RefreshDatabase` trait
4. Follow the naming convention: `test_feature_behavior`
5. Use proper assertions for each scenario
6. Run Pint formatting: `vendor/bin/pint --dirty`

## Known Issues

Database migration compatibility with SQLite may require schema updates. See DEPLOYMENT.md for database setup instructions.
