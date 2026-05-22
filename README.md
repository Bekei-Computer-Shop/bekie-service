# Bekie Service E-commerce API

A professional REST API foundation for the Bekie e-commerce platform built with Laravel 12.

## Overview

This project provides:

- Versioned API routes under `api/v1`
- Product catalog endpoints for categories, brands, products, and variants
- Cart management for guest and authenticated contexts
- Wishlist CRUD and item management
- Order checkout flow with shipping calculation
- Coupon validation endpoint
- Shipping method discovery
- Swagger API documentation available through the browser

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

If you are using Sail or Docker, adapt the commands to your environment.

## Development

Run the application locally:

```bash
php artisan serve
```

If you use frontend assets or build tooling, use:

```bash
npm install
npm run build
```

## API Documentation

The API is documented with Swagger/OpenAPI. Access the docs in your browser at:

- `http://localhost:8000/api/docs`
- `http://localhost:8000/api/admin/docs`

The raw OpenAPI definitions are available at:

- `http://localhost:8000/openapi.json`
- `http://localhost:8000/openapi-admin.json`

## API Structure

All endpoints are versioned under:

- `GET /api/v1/categories`
- `GET /api/v1/brands`
- `GET /api/v1/products`
- `GET /api/v1/products/{product}`
- `GET /api/v1/shipping-methods`
- `POST /api/v1/coupons/apply`
- `GET /api/v1/carts`
- `POST /api/v1/carts`
- `GET /api/v1/carts/{cart}`
- `POST /api/v1/carts/{cart}/items`
- `PATCH /api/v1/carts/{cart}/items/{item}`
- `DELETE /api/v1/carts/{cart}/items/{item}`
- `POST /api/v1/carts/{cart}/checkout`
- `GET /api/v1/wishlists`
- `POST /api/v1/wishlists`
- `GET /api/v1/wishlists/{wishlist}`
- `DELETE /api/v1/wishlists/{wishlist}`
- `POST /api/v1/wishlists/{wishlist}/items`
- `DELETE /api/v1/wishlists/{wishlist}/items/{item}`
- `GET /api/v1/orders`
- `POST /api/v1/orders`
- `GET /api/v1/orders/{order}`

## Admin Panel API Structure

Admin endpoints are scoped under `api/v1/admin` and organized separately from the customer-facing mobile/web API.

- `GET /api/v1/admin/dashboard`
- `GET /api/v1/admin/products`
- `GET /api/v1/admin/orders`
- `GET /api/v1/admin/customers`
- `GET /api/v1/admin/settings`

The admin API uses dedicated namespaces and folder structure:

- `routes/api_admin.php` – admin API route definitions
- `app/Http/Controllers/Api/Admin/V1` – admin panel controllers
- `app/Http/Requests/Api/Admin/V1` – admin request validation classes
- `app/Http/Resources/Api/Admin/V1` – admin response resources

## Key Project Files

- `routes/api.php` – API route definitions for version 1
- `bootstrap/app.php` – registers API routing in the Laravel bootstrap
- `app/Http/Controllers/Api/Client/V1` – client API controllers for mobile/web clients
- `app/Http/Requests/Api/Client/V1` – client API request validation classes
- `app/Http/Resources/Api/Client/V1` – client API response resources
- `app/Http/Controllers/Api/Admin/V1` – admin panel controllers
- `app/Http/Requests/Api/Admin/V1` – admin panel request validation classes
- `app/Http/Resources/Api/Admin/V1` – admin panel response resources
- `resources/views/swagger.blade.php` – Swagger UI wrapper page
- `public/openapi.json` – OpenAPI definition for the client API
- `public/openapi-admin.json` – OpenAPI definition for the admin API

## Features Included

- Standard REST Endpoints for ecommerce catalog and checkout
- Clean versioned API routing
- Structured resource responses
- Request validation via FormRequest classes
- Swagger documentation with a browser interface
- Cart and order workflows following professional e-commerce patterns

## Professional API Features

### 1. Pagination & Filtering
- **Limit/Offset Pagination**: All list endpoints support `limit` (max 100) and `offset` parameters
- **Reusable Pagination Trait**: `PaginatesApiRequests` trait for consistent pagination across controllers
- **Master Data Endpoints**: Lightweight, cached endpoints for frequently accessed data:
  - `GET /api/v1/master/categories` – Categories list with pagination
  - `GET /api/v1/master/brands` – Brands list with pagination
  - `GET /api/v1/master/shipping-methods` – Shipping methods list with pagination

### 2. Security Enhancements
- **API Security Headers**: All responses include security headers:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `X-XSS-Protection: 1; mode=block`
  - `Strict-Transport-Security: max-age=31536000`
  - `Cache-Control: no-store` (prevents caching of sensitive data)
- **Request Validation**: All API requests validate input using FormRequest classes
- **Token-Based Authentication**: Secure SHA256-hashed bearer tokens with configurable expiration
- **Admin Role Middleware**: `AdminRoleMiddleware` enforces admin-only access to sensitive endpoints

### 3. Strong Admin Authentication
- **Dedicated Admin Auth Service**: `AdminAuthService` with role-based access control
- **Extended Token Expiration**: 
  - Access tokens: 120 minutes
  - Refresh tokens: 30 days
- **Scoped Tokens**: `scope` field in api_tokens distinguishes between `client` and `admin` tokens
- **Admin Dashboard**: `/api/v1/admin/dashboard` returns key metrics:
  - Total orders, revenue, products, customers
  - Today's statistics
  - This month's statistics
  - This year's statistics
- **Separate Admin Routes**: Dedicated route file `routes/api_admin.php` with strict access control

### 4. Professional Request/Response Headers
- **Automatic JSON Format**: `EnsureJsonResponse` middleware ensures all responses are JSON
- **Content-Type Headers**: All responses include `Content-Type: application/json`
- **Consistent Response Format**:
  ```json
  {
    "status": "success|error",
    "message": "Operation message",
    "data": { ... }
  }
  ```

### 5. Comprehensive Swagger Documentation
- **Dual API Specs**: Separate OpenAPI 3.0 specs for client and admin APIs
- **Mock Data Examples**: All endpoints include example responses with realistic mock data
- **Parameter Documentation**: Query parameters, headers, and body schemas fully documented
- **Separate Documentation URLs**:
  - Client API: `http://localhost:8000/api/docs`
  - Admin API: `http://localhost:8000/api/admin/docs`
  - Raw specs: `/openapi.json` and `/openapi-admin.json`

### 6. Database Migrations
New migrations support admin features:
- `2026_05_20_000000_add_is_admin_to_users_table.php` – Adds `is_admin` boolean to users
- `2026_05_20_000001_add_scope_to_api_tokens_table.php` – Adds `scope` field to api_tokens

### 7. New Traits & Utilities
- `PaginatesApiRequests` trait – Handles limit/offset pagination logic
- `EnsureJsonResponse` middleware – Ensures JSON response format
- `ApiSecurityHeaders` middleware – Adds security headers to all responses
- `AdminRoleMiddleware` – Protects admin endpoints with role check

## Running Migrations
After pulling the latest changes, run:
```bash
php artisan migrate
```

This applies the new admin fields and scope tracking to your database.

## Next Improvements

Future enhancements can include:

- Payment gateway integration
- Customer account and address management APIs
- Order fulfillment and shipment tracking workflows
- Inventory reservation and stock management
- Rate limiting per IP/user
- API usage analytics and monitoring
- WebSocket support for real-time notifications

## License

MIT
