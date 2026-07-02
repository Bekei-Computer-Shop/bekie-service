# API Reference

The Bekie service exposes a versioned, JWT-secured REST API. This document is the human-readable companion to the OpenAPI specs (`public/openapi.json` and `public/openapi-admin.json`). For schemas, request/response examples, and "try it out," use the browsable docs.

| Surface | OpenAPI spec | Swagger UI | Redoc |
|---|---|---|---|
| Client (mobile/web) | `/openapi.json` | `/api/docs` | `/api/docs/redoc` |
| Admin panel | `/openapi-admin.json` | `/api/admin/docs` | `/api/admin/docs/redoc` |

---

## Getting Started

### Base URL

```
http://localhost:8000/api/v1          # local development
https://api.bekie.com/api/v1           # production (admin under /admin)
```

### Authentication flow

1. **Register or login** to obtain a token pair:
   - `POST /api/v1/auth/register` — public, creates a customer
   - `POST /api/v1/auth/login` — public, returns `{access_token, refresh_token, token_type, expires_at}`
   - `POST /api/v1/admin/auth/login` — admin variant, requires `is_admin=true`, `is_active=true`, `is_banned=false`, and the `admin` Spatie role

2. **Send the access token** on every authenticated request:

   ```
   Authorization: Bearer <access_token>
   ```

3. **Refresh before expiry.** Access tokens live 60 minutes (client) or 120 minutes (admin); refresh tokens live 7 days (client) or 30 days (admin). Hit `POST /auth/refresh` (or `/admin/auth/refresh`) with the refresh token to get a fresh pair.

4. **Logout** to revoke: `POST /auth/logout` — the current access token is marked revoked in `api_tokens` and can no longer authenticate.

### Token scopes

The `api_tokens.scope` column distinguishes token audiences:

| Scope | Audience | Middleware | Endpoint prefix |
|---|---|---|---|
| `client` | Customer-facing mobile/web | `AuthenticateApiToken` | `/api/v1/...` |
| `admin` | Admin panel | `AuthenticateAdminApiToken` | `/api/v1/admin/...` |

A client token used against an admin endpoint (or vice versa) returns `401 Invalid or expired admin access token.`

---

## Response Envelope

Every response — success or error — is JSON and follows the same envelope:

```json
{
  "status": "success",
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

```json
{
  "status": "error",
  "message": "Invalid credentials.",
  "errors": { }
}
```

- `status` is always `"success"` or `"error"`.
- `message` is human-readable and safe to surface in UIs.
- `errors` is a map keyed by field name (for validation errors) or an empty object `{}` for general errors.
- `data` is omitted on success-only-no-content endpoints; those return `204 No Content` with an empty body.

---

## Error Codes

| HTTP | When | Example message |
|---|---|---|
| `200` | Successful GET / PATCH / DELETE | `Operation completed successfully.` |
| `201` | Resource created | `Registration successful.` |
| `204` | No content (successful delete) | (empty body) |
| `400` | Bad request (malformed) | `Bad request.` |
| `401` | Missing or invalid token | `Authorization bearer token is required.` |
| `403` | Token valid but caller not permitted | `Unauthorized: Admin access required.` |
| `404` | Resource not found | `Resource not found.` |
| `422` | Validation or business rule failure | `Cart is empty.` |
| `500` | Server error | `Server error.` |

---

## Pagination

### Master data (`/api/v1/master/*`)

Limit/offset pagination. Default `limit=20`, `offset=0`. `limit` is capped at 100.

```bash
GET /api/v1/master/categories?limit=20&offset=40
```

```json
{
  "status": "success",
  "message": "Master categories retrieved successfully.",
  "data": [ ... ],
  "pagination": {
    "total": 150,
    "limit": 20,
    "offset": 40,
    "count": 20
  }
}
```

### Standard list endpoints

Use the Laravel-style `page` parameter; the response is a paginator (`data`, `links`, `meta`). Per-page defaults vary by endpoint: products `18`, categories `16`, carts `15`.

```bash
GET /api/v1/products?page=2&category_id=7&search=macbook
```

---

## Client API Endpoints

### Authentication (`/api/v1/auth/*`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/auth/register` | public | Create a customer; returns token pair + user |
| `POST` | `/auth/login` | public | Authenticate; returns token pair |
| `POST` | `/auth/refresh` | public | Exchange refresh token for a new pair |
| `POST` | `/auth/logout` | client | Revoke the current access token |

### Master Data (`/api/v1/master/*`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/master/categories` | public | Paginated categories (cached) |
| `GET` | `/master/brands` | public | Paginated brands (cached) |
| `GET` | `/master/shipping-methods` | public | Paginated shipping methods (cached) |

### Catalog (`/api/v1/categories|brands|products|shipping-methods`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/categories` | public | Root categories with children |
| `GET` | `/categories/{category}` | public | Category detail |
| `GET` | `/brands` | public | Paginated brands |
| `GET` | `/brands/{brand}` | public | Brand detail |
| `GET` | `/products` | public | Paginated products (filters: `category_id`, `brand_id`, `search`) |
| `GET` | `/products/{product}` | public | Product detail with category, brand, variants |
| `GET` | `/products/{product}/variants` | public | Active variants for a product |
| `GET` | `/shipping-methods` | public | Active shipping methods |

### Carts (`/api/v1/carts/*`, client auth)

| Method | Path | Description |
|---|---|---|
| `GET` | `/carts` | List carts (filters: `session_id`, `user_id`) |
| `POST` | `/carts` | Create or retrieve a cart (idempotent on session/user) |
| `GET` | `/carts/{cart}` | Cart with items |
| `POST` | `/carts/{cart}/items` | Add or update a cart line |
| `PATCH` | `/carts/{cart}/items/{item}` | Update quantity |
| `DELETE` | `/carts/{cart}/items/{item}` | Remove line |
| `POST` | `/carts/{cart}/checkout` | Convert cart to order; decrements inventory |

### Wishlists (`/api/v1/wishlists/*`, client auth)

| Method | Path | Description |
|---|---|---|
| `GET` | `/wishlists` | List the caller's wishlists |
| `POST` | `/wishlists` | Create a wishlist |
| `GET` | `/wishlists/{wishlist}` | Wishlist detail |
| `DELETE` | `/wishlists/{wishlist}` | Delete a wishlist |
| `POST` | `/wishlists/{wishlist}/items` | Add a product |
| `DELETE` | `/wishlists/{wishlist}/items/{item}` | Remove a product |

### Orders (`/api/v1/orders/*`, client auth)

| Method | Path | Description |
|---|---|---|
| `GET` | `/orders` | List orders |
| `POST` | `/orders` | Create an order |
| `GET` | `/orders/{order}` | Order detail with items and snapshots |

### Coupons (`/api/v1/coupons/*`, client auth)

| Method | Path | Description |
|---|---|---|
| `POST` | `/coupons/apply` | Validate and apply a coupon code |

---

## Admin API Endpoints

All admin endpoints require an admin-scoped Bearer token. Admin tokens are issued by `POST /api/v1/admin/auth/login` and last 120 minutes; refresh tokens last 30 days.

### Admin authentication (`/api/v1/admin/auth/*`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/admin/auth/login` | public | Admin login; returns token pair + admin user |
| `POST` | `/admin/auth/refresh` | public | Exchange refresh token for a new pair |
| `GET` | `/admin/auth/me` | admin | Current admin profile |
| `POST` | `/admin/auth/logout` | admin | Revoke the current token |
| `POST` | `/admin/auth/change-password` | admin | Change own password |

### Media Management (`/api/v1/admin/media`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/admin/media` | admin | Upload a file (multipart/form-data). Accepts `file` and optional `folder`. Returns a URL. |

### Dashboard (`/api/v1/admin/dashboard`)

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/admin/dashboard` | admin | Aggregated KPIs: order/revenue/product/customer totals, plus today / this month / this year slices |

### Admin product / order / customer management

Endpoints for product CRUD, bulk actions, order management, and customer management are referenced in `routes/api_admin.php` and will be added to this reference and to `openapi-admin.json` as their controllers are implemented. The route file currently references `App\Http\Controllers\Api\Admin\V1\ProductController` for an `apiResource('products')` plus `PATCH /products/{product}/status`, `POST /products/bulk-status`, and `POST /products/bulk-delete`.

---

## Security Notes

- **TLS everywhere in production.** HSTS is applied to every response with `max-age=31536000; includeSubDomains`.
- **JWT (HS256)** tokens are issued by `JwtService` (`app/Services/JwtService.php`). The `jti` claim is the lookup key — it's stored as a SHA256 hash in `api_tokens.token`, never in plaintext.
- **No-store caching.** Responses set `Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate` to prevent caching of sensitive payloads (especially auth responses).
- **Clickjacking protection.** `X-Frame-Options: DENY` on all API responses.
- **MIME-type sniffing protection.** `X-Content-Type-Options: nosniff`.
- **Admin gating.** Beyond token validity, `AuthenticateAdminApiToken` enforces `is_admin && is_active && !is_banned`. A banned admin user still holding a valid token gets a `403 Unauthorized: Admin access required.`
- **Race-condition handling on registration.** `POST /auth/register` checks for existing email/phone before insert and returns a clean `422` on collision instead of relying on a unique-index violation.
- **Order audit trail.** Orders store `customer_snapshot` and `address_snapshot` JSON columns at checkout time so historical order data is preserved even when users or addresses change later.

---

## Examples

### Register and store the token

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","first_name":"Jane","last_name":"Doe","password":"SecurePassword123"}'
```

```json
{
  "status": "success",
  "message": "Registration successful.",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLC...",
    "refresh_token": "rt_8f3a2b1c...",
    "token_type": "Bearer",
    "expires_at": "2026-06-25 14:30:00",
    "user": { "id": 42, "email": "user@example.com", "first_name": "Jane", "last_name": "Doe", "is_active": true, "is_banned": false }
  }
}
```

### Browse products with filters

```bash
curl "http://localhost:8000/api/v1/products?category_id=7&brand_id=12&search=macbook"
```

### Add to cart (authenticated)

```bash
curl -X POST http://localhost:8000/api/v1/carts/12/items \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{"product_id":55,"product_variant_id":101,"quantity":2}'
```

### Checkout

```bash
curl -X POST http://localhost:8000/api/v1/carts/12/checkout \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{
    "shipping_method_id": 1,
    "address_id": 17,
    "recipient_name": "Jane Doe",
    "email": "user@example.com",
    "phone": "+15551234567"
  }'
```

### Admin login and dashboard

```bash
curl -X POST http://localhost:8000/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@bekie.com","password":"SecurePassword123"}'
```

```bash
curl http://localhost:8000/api/v1/admin/dashboard \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..."
```

---

## See Also

- `README.md` — installation, env setup, deployment notes
- `API_IMPROVEMENTS_SUMMARY.md` — historical record of API improvements (pagination, master data, security headers, admin auth)
- `PROJECT_STRUCTURE_SUMMARY.md` — model relationships, controllers, services, and patterns
- `CLAUDE.md` — architecture guide for AI-assisted development
