# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Bekie Service** — Laravel 12 REST API backend for the Bekie e-commerce platform. PHP 8.3, MySQL/SQLite, JWT-based bearer auth, dual surface area (customer-facing client API + admin panel API). This is a pure API service — no traditional server-rendered views.

## Tech Stack & Versions

- Laravel framework v12 (streamlined file structure; no `app/Http/Kernel.php`, no `app/Console/Kernel.php`)
- PHP `^8.3`
- `laravel/sanctum` v4, `spatie/laravel-permission` v6 (roles on User model)
- `maatwebsite/excel`, `barryvdh/laravel-dompdf`
- **Testing**: Pest v3 (with `pestphp/pest-plugin-laravel`); PHPUnit v11 under the hood
- **Formatting**: Laravel Pint v1
- **Frontend tooling**: Vite v7 + TailwindCSS v4 (assets only — no SPA)

## Common Commands

```bash
# Install + first-run setup (copies .env, generates key, migrates, builds assets)
composer setup

# Dev stack: PHP server + queue worker + Vite (all in parallel via concurrently)
composer run dev

# Tests
composer test                                   # full suite (clears config first)
php artisan test --compact                      # compact output
php artisan test --compact --filter=CreateUser  # single test by name
php artisan make:test --pest SomeFeatureTest    # generate a feature test (Pest)
php artisan make:test --pest --unit SomeUnitTest

# Formatting (REQUIRED after any PHP edit per AGENTS.md boost rules)
vendor/bin/pint --dirty

# Routes / config / debugging
php artisan route:list --path=api
php artisan config:show database.default
php artisan tinker --execute 'Your::code();'    # single-quote to avoid shell expansion
```

If a frontend change doesn't appear, ask the user to run `npm run build`, `npm run dev`, or `composer run dev`.

## Architecture Overview

### Dual API Surface

Two completely separate route files, both registered in `bootstrap/app.php` (the Laravel 12 way — no `Kernel.php`):

| Surface | Route file | Prefix | Controllers |
|---|---|---|---|
| Client (mobile/web customers) | `routes/api.php` | `/api/v1` | `app/Http/Controllers/Api/Client/V1/` |
| Admin (panel) | `routes/api_admin.php` | `/api/v1/admin` | `app/Http/Controllers/Api/Admin/V1/` |

The admin route file is loaded **from inside** `routes/api.php` (`require __DIR__.'/api_admin.php';`), so it inherits the `/v1` prefix automatically.

### Authentication Flow

All auth is custom JWT — there is **no** Laravel Sanctum usage in the request flow despite the package being installed.

- `JwtService` (`app/Services/JwtService.php`) — encodes/decodes JWT bearer tokens
- `ApiToken` model — persists a SHA256 hash of the JWT `jti`, with `scope` column (`client` | `admin`), `revoked` flag, and `expires_at`/`refresh_expires_at` timestamps
- `AuthService` (`app/Services/AuthService.php`) — client-side token lifecycle (create/refresh/revoke)
- `AdminAuthService` (`app/Services/AdminAuthService.php`) — admin-side token lifecycle with longer expirations (120 min access, 30 day refresh)

Two middlewares gate routes:
- `AuthenticateApiToken` (`app/Http/Middleware/AuthenticateApiToken.php`) — for client routes; looks up `scope=client` tokens
- `AuthenticateAdminApiToken` — for admin routes; looks up `scope=admin` tokens AND verifies `user->is_admin`, `is_active`, not `is_banned`

Both store the resolved `ApiToken` and `User` on `$request->attributes` (keys `api_token`, `authenticated_user`) so downstream controllers can read them.

### Global API Middleware

Registered in `bootstrap/app.php` via `withMiddleware()` and applied to every API response:

1. `EnsureJsonResponse` — forces `Accept: application/json`
2. `ApiSecurityHeaders` — sets `X-Content-Type-Options`, `X-Frame-Options`, HSTS, cache-control headers

### Response Format

Every controller extends `BaseApiController` (or `BaseAdminController` for admin). Use these helpers instead of `response()->json()`:

```php
$this->success($data, $message);          // 200, {status:'success', message, data}
$this->created($data, $message);          // 201
$this->noContent();                       // 204
$this->error($message, $status, $errors); // {status:'error', message, errors}
```

Pagination via `PaginatesApiRequests` trait (`app/Traits/`) returns `{items: [...], pagination: {total, limit, offset, count}}`. Master-data endpoints (`/api/v1/master/*`) use this trait.

### Domain Patterns

- **Denormalization for audit**: `CartItem` stores `product_name`, `product_sku`, `variant_name`, `variant_attributes` so cart history survives product edits. `Order` stores `customer_snapshot` and `address_snapshot` for the same reason.
- **SoftDeletes** on most catalog/order models — never `->delete()` blindly; use `->forceDelete()` only when intentional.
- **Casts in `casts()` method**, not the `$casts` property (Laravel 12 convention).
- **Scopes** on models: `ShippingMethod` (`active`, `type`, `ordered`), `Coupon` (`active`, `valid`), `Review` (`approved`, `verified`, `forProduct`), `Wishlist` (`active`, `public`, `forUser`, `forSession`).
- **Eager loading** is mandatory in controllers to avoid N+1 (`Product::with(['category','brand'])` pattern).

### Form Requests & Resources (parallel structure)

- Client: `app/Http/Requests/Api/Client/V1/` and `app/Http/Resources/Api/Client/V1/`
- Admin: `app/Http/Requests/Api/Admin/V1/` and `app/Http/Resources/Api/Admin/V1/`

Resources use `whenLoaded()` so relationships are conditionally serialized.

### OpenAPI / Swagger

- Raw specs: `public/openapi.json` (client) and `public/openapi-admin.json` (admin)
- UI: `/api/docs` and `/api/admin/docs` via `resources/views/swagger.blade.php`
- When adding an endpoint, update the matching spec file in the same change.

## Laravel Boost Guidelines (AGENTS.md)

This repo has `boost.json` enabled and the guidelines are loaded as the agent system prompt. Key binding rules from it:

- **Run `vendor/bin/pint --dirty --format agent` after every PHP edit.** Do not use `--test`; let Pint fix issues.
- **Use `php artisan make:` for new files** (controllers, models, requests, resources, tests). Pass `--no-interaction`.
- **Use Pest**, not raw PHPUnit. Feature tests by default; `--unit` for unit tests. Do not delete tests without approval.
- **Follow Laravel 12 conventions**: middleware in `bootstrap/app.php`, casts in `casts()` method, Eloquent API Resources for API output, named routes via `route()` helper.
- **PHP style**: curly braces on all control structures, constructor property promotion, explicit return type declarations, TitleCase enum keys, PHPDoc with array shape type definitions.
- **Do not create verification scripts or tinker scripts** when a test covers the case. Use factories (`$this->faker->word()` or `fake()->randomDigit()`) over manual model creation in tests.
- **When modifying a column in a migration, include all prior column attributes** — they're lost otherwise.

## Migrations to Know About

Two recent migrations extend auth for the admin split:
- `2026_05_20_000000_add_is_admin_to_users_table.php` — `is_admin` boolean on `users`
- `2026_05_20_000001_add_scope_to_api_tokens_table.php` — `scope` varchar on `api_tokens`

`User::is_admin`, `User::is_active`, `User::is_banned`, and `ApiToken::scope` are part of the auth contract — don't rename without checking middleware assumptions.