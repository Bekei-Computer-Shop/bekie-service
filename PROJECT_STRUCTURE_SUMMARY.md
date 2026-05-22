# Laravel Project Structure Summary - Bekie Service

## 1. Model Relationships Overview

### Core E-Commerce Models

#### **Product Ecosystem**
```
Product
  ├── belongsTo(Category)
  ├── belongsTo(Brand)
  ├── hasMany(ProductVariant)
  └── hasMany(ProductImage)

ProductVariant
  └── belongsTo(Product)

ProductImage
  └── belongsTo(Product)
  └── hasAccessor: getUrlAttribute() → Storage disk URL

Category
  ├── belongsTo(Category, 'parent_id') [parent_id for hierarchy]
  └── hasMany(Category, 'parent_id') [children categories]

Brand
  └── hasMany(Product)
```

#### **User & Authentication**
```
User (Authenticatable with HasRoles)
  ├── hasMany(ApiToken)
  ├── belongsToMany(CustomerGroup)
  └── hasMany(Address)
  └── hasMany(Order)
  └── hasMany(Review)
  └── hasMany(Wishlist)
  └── hasMany(Cart)

ApiToken
  ├── belongsTo(User)
  └── methods: isExpired(), isRefreshExpired(), revoke()

Address
  └── belongsTo(User)
  [Supports multiple addresses per user with labels, types, defaults]
```

#### **Shopping Cart & Wishlist**
```
Cart
  ├── belongsTo(User, nullable)
  ├── belongsTo(Session via session_id)
  └── hasMany(CartItem)

CartItem
  ├── belongsTo(Cart)
  ├── belongsTo(Product)
  └── belongsTo(ProductVariant)
  [Stores denormalized data: product_name, product_sku, variant_name, variant_attributes]

Wishlist
  ├── belongsTo(User, nullable)
  ├── belongsTo(Session via session_id)
  └── hasMany(WishlistItem)
  [Supports both authenticated & guest wishlists with public/private access]

WishlistItem
  └── belongsTo(Wishlist)
```

#### **Orders & Transactions**
```
Order
  ├── belongsTo(User)
  ├── hasMany(OrderItem)
  [Includes snapshots: customer_snapshot, address_snapshot for audit trail]
  [Status fields: payment_status, shipping_status, tracking info]

OrderItem
  └── belongsTo(Order)
  [Line item details with pricing snapshots]

Shipment
  [Separate model for shipment tracking - relates to Order]

Transaction
  [Payment transaction records - relates to Order]
```

#### **Catalog Features**
```
Review
  ├── belongsTo(User)
  ├── belongsTo(Product)
  ├── belongsTo(Order, nullable)
  [Scopes: approved(), verified(), forProduct(), topRated()]

Coupon
  [Scopes: active(), valid(), canBeUsedByUser()]
  [Supports: discount types, usage limits, date ranges, category/product specificity]
  [Methods: isValid(), canBeUsedByUser()]

CouponUsage
  [Tracks coupon redemption per user]

ShippingMethod
  ├── Scopes: active(), type(), ordered()
  ├── Accessors: getFormattedPriceAttribute(), getEstimatedDeliveryAttribute()
  [Supports weight-based pricing, delivery time ranges]

CustomerGroup
  ├── belongsToMany(User)
```

#### **Other Models**
```
Page, Banner, Setting, Attribute, Faq, Carrier
```

---

## 2. Middleware Structure

### Current Middleware

**Location:** `app/Http/Middleware/`

#### **AuthenticateApiToken**
- **Purpose:** Bearer token authentication for API routes
- **Mechanism:**
  - Extracts bearer token from request headers
  - Hashes token and queries `api_tokens` table
  - Checks token expiration status
  - Returns 401 JSON response if invalid/expired
  - Sets authenticated user via `Auth::setUser()` and request resolver
  - Stores `api_token` in request attributes
- **Response Format:**
  ```json
  {
    "status": "error",
    "message": "Invalid or expired access token."
  }
  ```

### Middleware Configuration

**Location:** `bootstrap/app.php`
- Middleware registered via `Application::configure()->withMiddleware()`
- Currently minimal middleware configuration (empty)

---

## 3. Authentication Service Implementation

### AuthService Location
`app/Services/AuthService.php`

#### **Key Methods**

**1. `createToken(User $user, Request $request): array`**
- Generates 80-character random access token & refresh token
- Hashes tokens with SHA256
- Creates `ApiToken` record with:
  - 60-minute access token expiration
  - 7-day refresh token expiration
  - IP address & user agent tracking
  - Revoked flag (default: false)
- Returns: `[model, access_token, refresh_token, expires_at]`

**2. `refreshToken(string $refreshToken, Request $request): ?array`**
- Validates refresh token hasn't expired
- Issues new access token (60 min) and refresh token (7 days)
- Updates IP address & user agent
- Returns new token set or null if invalid

**3. `findActiveToken(string $token): ?ApiToken`**
- Queries for valid, non-revoked token by hashed value
- Returns ApiToken model or null

**4. `revokeToken(ApiToken $token): bool`**
- Sets `revoked` flag to true
- Used for logout functionality

#### **Token Storage**
- Model: `ApiToken`
- Hashing: SHA256 (tokens never stored in plain text)
- Expiration tracking via datetime fields
- Per-session tracking with IP & user agent

---

## 4. API Controller Patterns & Response Formats

### Controller Structure

**Base Controller:** `app/Http/Controllers/Api/Client/V1/BaseApiController`

#### **Response Methods**

All controllers inherit standardized response methods:

```php
protected function success(mixed $data = null, string $message = ''): JsonResponse
    // Returns: {status: 'success', message: '', data: {...}}
    // HTTP: 200

protected function created(mixed $data = null, string $message = ''): JsonResponse
    // Returns: {status: 'success', message: '', data: {...}}
    // HTTP: 201

protected function noContent(): JsonResponse
    // HTTP: 204 (No body)

protected function error(string $message = '', int $status = 400, array $errors = []): JsonResponse
    // Returns: {status: 'error', message: '', errors: {...}}
    // HTTP: 400 (or specified status)
```

### API Response Format
```json
{
  "status": "success|error",
  "message": "Optional message",
  "data": { /* payload */ },
  "errors": { /* validation errors */ }
}
```

### Current API Controllers (Client V1)

**Location:** `app/Http/Controllers/Api/Client/V1/`

#### **AuthController**
- `login(LoginRequest)` - Email/password auth
- `refresh(RefreshTokenRequest)` - Token refresh
- `logout()` - Token revocation

#### **ProductController**
- `index(Request)` - List products with filters/pagination (18 per page)
- `show(Product)` - Single product with relations
- `variants(Product)` - Get product variants

#### **CategoryController**
- `index(Request)` - List root categories with children (16 per page)
- `show(Category)` - Single category with children

#### **BrandController**
- `index(Request)` - List brands (paginated)
- `show(Brand)` - Single brand

#### **CartController**
- `index(Request)` - List carts (15 per page) with filtering
- `store(Request)` - Create cart
- `show(Cart)` - Single cart with items
- `addItem(AddCartItemRequest, Cart)` - Add/update item
- `updateItem(UpdateCartItemRequest, Cart, CartItem)` - Update quantity/details
- `removeItem(Cart, CartItem)` - Remove from cart
- `checkout(StoreOrderRequest, Cart)` - Convert to order

#### **WishlistController**
- `index(Request)` - List wishlists
- `store(StoreWishlistRequest)` - Create wishlist
- `show(Wishlist)` - Single wishlist
- `destroy(Wishlist)` - Delete wishlist

#### **CouponController**
- `apply(ApplyCouponRequest)` - Validate & apply coupon

#### **ShippingMethodController**
- `index(Request)` - List active shipping methods

#### **OrderController**
- Handles order operations (CRUD, status updates)

### API Resources (Client V1)

**Location:** `app/Http/Resources/Api/Client/V1/`

All resources extend `JsonResource` and use `whenLoaded()` for conditional relationships:

- **ProductResource** - Full product with category, brand, variants
- **ProductVariantResource** - Variant details with attributes
- **CategoryResource** - Category with children
- **BrandResource** - Brand info
- **CartResource** - Cart summary with items (denormalized)
- **CartItemResource** - Cart item with product/variant
- **OrderResource** - Order with items and snapshots
- **OrderItemResource** - Order line item
- **WishlistResource** - Wishlist with items
- **WishlistItemResource** - Wishlist item
- **ShippingMethodResource** - Shipping method details

### Admin Controllers (V1)

**Location:** `app/Http/Controllers/Api/Admin/V1/`

- **BaseAdminController** - Base class (minimal, extensible)
- **DashboardController** - Dashboard stats (stub implementation)

### Request Validation Classes

**Location:** `app/Http/Requests/Api/Client/V1/`

- `LoginRequest` - Email + Password
- `RefreshTokenRequest` - Refresh token
- `AddCartItemRequest` - Product, variant, quantity
- `UpdateCartItemRequest` - Updated quantity/price
- `StoreOrderRequest` - Full order data
- `StoreWishlistRequest` - Wishlist name/description
- `AddWishlistItemRequest` - Add product to wishlist
- `ApplyCouponRequest` - Coupon code

---

## 5. Pagination & Filtering Logic

### Pagination Configuration

**Defaults:**
- Products: 18 items per page
- Categories: 16 items per page
- Carts: 15 items per page

**Usage:** Standard Laravel `->paginate(n)` method

### Filtering Patterns

#### **Product Filtering** (ProductController::index)
```php
// Query parameters
- category_id: Filter by category
- brand_id: Filter by brand
- search: Full-text search on name, slug, short_description
- is_active: Only active products

// Query building
$query->where('is_active', true)
    ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->query('category_id')))
    ->when($request->filled('brand_id'), fn($q) => $q->where('brand_id', $request->query('brand_id')))
    ->when($request->filled('search'), fn($q) => $q->where(function($builder) use ($search) {
        $builder->where('name', 'like', "%{$search}%")
            ->orWhere('slug', 'like', "%{$search}%")
            ->orWhere('short_description', 'like', "%{$search}%");
    }))
    ->orderBy('is_featured', 'desc')
    ->orderBy('sort_order')
    ->paginate(18);
```

#### **Cart Filtering** (CartController::index)
```php
// Query parameters
- session_id: Filter by guest session
- user_id: Filter by user
- Returned ordered by updated_at DESC

$query->when($request->filled('session_id'), fn($q) => $q->where('session_id', $request->query('session_id')))
    ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->query('user_id')))
    ->orderBy('updated_at', 'desc')
    ->paginate(15);
```

#### **Category Filtering** (CategoryController::index)
```php
// Returns only root categories with children relationship loaded
Category::with('children')
    ->where('is_active', true)
    ->whereNull('parent_id')
    ->orderBy('sort_order')
    ->paginate(16);
```

### Model Query Scopes

**ShippingMethod Scopes:**
```php
->active()          // where('is_active', true)
->type($type)       // where('type', $type)
->ordered()         // orderBy('sort_order')->orderBy('name')
```

**Coupon Scopes:**
```php
->active()          // where('is_active', true)
->valid()           // where starts_at <= now and expires_at >= now
```

**Review Scopes:**
```php
->approved()        // where('status', 'approved')
->verified()        // where('is_verified_purchase', true)
->forProduct($id)   // where('product_id', $id)
->topRated()        // [Additional scope]
```

**Wishlist Scopes:**
```php
->active()          // where('is_active', true)
->public()          // where('is_public', true)
->forUser($id)      // where('user_id', $id)
->forSession($id)   // where('session_id', $id)
```

### Eager Loading Patterns

Controllers use `with()` to prevent N+1 queries:

```php
Product::with(['category', 'brand'])
Product::with(['category', 'brand', 'variants'])
Cart::with('items.product', 'items.variant')
Category::with('children')
```

### Denormalization for Performance

**CartItem** stores denormalized product data:
```php
'product_name', 'product_sku', 'variant_name', 'variant_attributes'
```

**Order** stores snapshots:
```php
'customer_snapshot'  // array - buyer info at purchase time
'address_snapshot'   // array - shipping address at purchase time
```

This prevents data consistency issues when products/addresses change after order creation.

---

## 6. API Routes Structure

### Client API Routes
**File:** `routes/api.php`

```
/v1
  POST /auth/login
  POST /auth/refresh
  POST /auth/logout (protected)
  
  GET  /categories
  GET  /categories/{category}
  
  GET  /brands
  GET  /brands/{brand}
  
  GET  /products
  GET  /products/{product}
  GET  /products/{product}/variants
  
  GET  /shipping-methods
  
  (Protected routes below require AuthenticateApiToken)
  
  POST /coupons/apply
  
  /carts
    GET    /
    POST   /
    GET    /{cart}
    POST   /{cart}/items
    PATCH  /{cart}/items/{item}
    DELETE /{cart}/items/{item}
    POST   /{cart}/checkout
  
  /wishlists
    GET    /
    POST   /
    GET    /{wishlist}
    DELETE /{wishlist}
    POST   /{wishlist}/items
    DELETE /{wishlist}/items/{item}
  
  /orders
    GET    /
    GET    /{order}
    POST   /{order}/cancel
    POST   /{order}/refund
```

### Admin API Routes
**File:** `routes/api_admin.php`

```
/v1/admin (requires AuthenticateApiToken)
  GET  /dashboard
  
  Future routes:
  /products, /orders, /customers, etc.
```

---

## 7. Key Patterns & Conventions

### 1. **Soft Deletes**
Most models use `SoftDeletes` trait for safe deletion:
```php
Product, Category, Brand, Cart, Order, etc.
```

### 2. **Timestamps & Status Tracking**
Orders track multiple timestamps:
```php
created_at, updated_at, paid_at, shipped_at, delivered_at, cancelled_at, refunded_at
```

### 3. **Type Casting**
Consistent use of casts() method (Laravel 12 pattern):
```php
protected function casts(): array {
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'metadata' => 'array',
        'price' => 'decimal:2',
    ];
}
```

### 4. **UUID vs Integer IDs**
- Standard integer IDs for models
- UUID for session_id (cart/wishlist sessions)

### 5. **Normalized vs Denormalized Data**
- **Normalized:** Live product/category relationships
- **Denormalized:** CartItem stores product snapshots, Order stores customer/address snapshots

### 6. **Authentication**
- Token-based API auth (no sessions)
- Spatie Permission integration on User model

### 7. **Validation**
Form requests in `app/Http/Requests/Api/Client/V1/`

### 8. **Resource Transformation**
All API responses use Eloquent API Resources for consistent formatting

### 9. **Error Handling**
Standardized error responses via BaseApiController methods

---

## 8. File Structure Summary

```
app/
├── Models/ (28 models)
│   ├── User.php (with HasRoles)
│   ├── Product.php (with SoftDeletes, relationships)
│   ├── Category.php (hierarchical)
│   ├── Brand.php
│   ├── Cart.php & CartItem.php
│   ├── Order.php & OrderItem.php (with snapshots)
│   ├── Wishlist.php & WishlistItem.php
│   ├── ApiToken.php (token management)
│   ├── Review.php (ratings/reviews)
│   ├── Coupon.php & CouponUsage.php
│   ├── ShippingMethod.php (with scopes)
│   ├── Address.php
│   ├── ProductVariant.php & ProductImage.php
│   └── ... (other models)
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/Client/V1/
│   │   │   ├── BaseApiController.php (response methods)
│   │   │   ├── ProductController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── BrandController.php
│   │   │   ├── CartController.php
│   │   │   ├── WishlistController.php
│   │   │   ├── OrderController.php
│   │   │   ├── AuthController.php
│   │   │   ├── CouponController.php
│   │   │   └── ShippingMethodController.php
│   │   │
│   │   └── Api/Admin/V1/
│   │       ├── BaseAdminController.php
│   │       └── DashboardController.php
│   │
│   ├── Middleware/
│   │   └── AuthenticateApiToken.php (bearer token validation)
│   │
│   ├── Requests/Api/Client/V1/
│   │   ├── LoginRequest.php
│   │   ├── AddCartItemRequest.php
│   │   ├── UpdateCartItemRequest.php
│   │   ├── StoreOrderRequest.php
│   │   ├── ApplyCouponRequest.php
│   │   └── ... (other form requests)
│   │
│   └── Resources/Api/Client/V1/
│       ├── ProductResource.php
│       ├── CartResource.php
│       ├── OrderResource.php
│       ├── CategoryResource.php
│       └── ... (other resources)
│
├── Services/
│   └── AuthService.php (token creation/refresh/validation)
│
└── Providers/
    └── AppServiceProvider.php

bootstrap/
├── app.php (middleware/exception config)
└── providers.php

routes/
├── api.php (Client v1 API)
├── api_admin.php (Admin v1 API)
├── web.php
└── console.php

config/
├── app.php
├── auth.php
├── database.php
└── ... (standard Laravel configs)
```

---

## 9. Technology Stack

- **Framework:** Laravel 12
- **PHP:** 8.2
- **Authentication:** Token-based (ApiToken model)
- **Authorization:** Spatie Permission
- **API Response:** Eloquent API Resources
- **Validation:** Form Requests
- **Database:** Configurable (likely MySQL via database.json)
- **Testing:** Pest v3, PHPUnit v11
- **Frontend Build:** Vite v4, TailwindCSS v4
- **Code Formatting:** Laravel Pint v1

---

## 10. Current Implementation Status

✅ **Completed:**
- Model structure with relationships
- Bearer token authentication
- Client API (v1) - CRUD for products, categories, brands, carts, wishlists, orders
- Admin API structure (minimal, extensible)
- Request validation classes
- API resources for response formatting
- Filtering & pagination patterns
- SoftDeletes on core models
- Snapshot storage for orders

🔄 **In Progress/Extensible:**
- Admin panel controllers (dashboard only, marked for expansion)
- Additional business logic (reviews, shipments, transactions)

📋 **Notes:**
- API uses simple numeric pagination (not cursor-based)
- Token expiration: 60 min access, 7 days refresh
- No role-based middleware yet (HasRoles trait present but not enforced)
- UUIDs used only for session tracking, not primary keys
