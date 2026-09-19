# Pre-Production Database Setup Guide

This guide explains how to set up a realistic pre-production environment with sample data that mimics a real e-commerce system.

## Overview

The pre-production seeder creates:
- ✅ **5 realistic customer accounts** with addresses
- ✅ **10 product categories** (Electronics, Fashion, Home & Garden, etc.)
- ✅ **8 featured products** with multiple variants (e.g., size/color/storage variations)
- ✅ **12 sample orders** with various statuses and payment states
- ✅ **Shopping carts** for each customer with active products
- ✅ **Wishlists** with products saved by customers
- ✅ **4 active promotional coupons** with different discount types
- ✅ **Product images** and proper inventory management
- ✅ **Admin account** for management panel access

## Quick Start

### Option 1: Fresh Database (Recommended for first setup)

```bash
php artisan seed:production --fresh
```

This command will:
1. Drop all existing tables
2. Run migrations to create fresh schema
3. Seed all permissions and admin user
4. Populate with pre-production data

### Option 2: Add to Existing Database

```bash
php artisan seed:production
```

This command will:
1. Seed permissions and admin user (if not exist)
2. Add pre-production data to current database

## Default Accounts

### Admin Account
```
Email:    admin@example.com
Password: password
Role:     Admin
```

Access the admin panel at `/api/admin/docs` (after authentication)

### Customer Accounts

| Name | Email | Password |
|------|-------|----------|
| John Doe | john.doe@example.com | password |
| Jane Smith | jane.smith@example.com | password |
| Michael Johnson | michael.johnson@example.com | password |
| Sarah Williams | sarah.williams@example.com | password |
| Robert Brown | robert.brown@example.com | password |

## Sample Data Structure

### Products with Variants

The seeder creates realistic product variants:

**MacBook Pro 16" M3 Max**
- 16" - 36GB Memory - 1TB SSD ($3,499)
- 16" - 36GB Memory - 2TB SSD ($3,899)
- 16" - 48GB Memory - 2TB SSD ($4,399)

**iPhone 15 Pro Max**
- Black - 256GB ($1,199)
- Black - 512GB ($1,299)
- Silver - 256GB ($1,199)
- Titanium Blue - 256GB ($1,199)

**Ultra Premium Cotton T-Shirt**
- Small - White ($49.99)
- Medium - White ($49.99)
- Large - White ($49.99)
- Small - Black ($49.99)
- Medium - Black ($49.99)

### Product Categories

- Electronics
- Computers & Laptops
- Smartphones
- Tablets
- Audio
- Fashion
- Men's Clothing
- Women's Clothing
- Home & Garden
- Sports & Outdoors

### Sample Orders

Sample orders are created with:
- Various statuses: pending, processing, shipped, delivered, cancelled
- Payment statuses: pending, paid, failed, refunded
- Multiple items per order
- Realistic pricing and discount application
- Customer and address snapshots for order history

### Active Coupons

| Code | Type | Discount | Min Purchase | Expiry |
|------|------|----------|--------------|--------|
| WELCOME10 | Percentage | 10% | $50 | +3 months |
| SAVE20 | Percentage | 20% | $100 | +1 month |
| FLAT15 | Fixed | $15 | $75 | +2 months |
| SUMMER30 | Percentage | 30% | $150 | +1 month |

## Database Statistics

After seeding, the database will contain:

```
Users:              5 customer + 1 admin = 6 total
Categories:         10
Products:           8 (with 30+ variants)
Orders:             12
Order Items:        ~25
Cart Items:         ~15 (across 5 carts)
Wishlist Items:     ~25
Coupons:            4 (active)
```

## API Testing with Sample Data

### Test Product Listing
```bash
GET /api/v1/products
GET /api/v1/categories
GET /api/v1/products/featured
```

### Test Cart Operations
```bash
# Login first
POST /api/v1/auth/login
{
  "email": "john.doe@example.com",
  "password": "password"
}

# Add product to cart
POST /api/v1/cart/items
{
  "product_id": "uuid-here",
  "quantity": 2
}

# Get cart
GET /api/v1/cart
```

### Test Order Creation
```bash
# Create order
POST /api/v1/orders
{
  "items": [
    {"product_id": "uuid-1", "quantity": 1},
    {"product_id": "uuid-2", "quantity": 2}
  ],
  "coupon_code": "WELCOME10"
}
```

### Admin Testing
```bash
# Login as admin
POST /api/v1/admin/auth/login
{
  "email": "admin@example.com",
  "password": "password"
}

# View orders
GET /api/v1/admin/orders

# View products
GET /api/v1/admin/products

# View customers
GET /api/v1/admin/customers
```

## Customizing Pre-Production Data

To modify the seeded data, edit `database/seeders/PreProductionSeeder.php`:

### Change Products
Modify the `$products` array in the `seedProducts()` method

### Change Categories
Modify the `$categoryData` array in the `seedCategories()` method

### Change Users/Customers
Modify the `$userData` array in the `seedUsers()` method

### Change Order Count
Modify the loop count in `seedOrders()` method:
```php
for ($i = 0; $i < 12; $i++) {  // Change 12 to desired count
```

## Advanced: Partial Seeding

If you only want to seed specific data, run individual seeders:

```bash
# Seed only products
php artisan db:seed --class=PreProductionSeeder

# Clear and reset
php artisan migrate:fresh --seed
```

## Environment Notes

### Database Configuration
Ensure your `.env` file has correct database credentials:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bekie_service
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### Redis/Cache (Optional)
Clear cache after seeding if needed:
```bash
php artisan cache:clear
php artisan config:clear
```

## Troubleshooting

### Foreign Key Constraint Errors
If you encounter foreign key errors, ensure migrations have run:
```bash
php artisan migrate
```

### Duplicate Key Errors
Clear the database first:
```bash
php artisan migrate:fresh
```

### UUID Conflicts
The seeder automatically generates unique UUIDs. If conflicts occur, verify UUID generation is working:
```bash
php artisan tinker
>>> Illuminate\Support\Str::uuid()
```

## Testing Checklist

- ✅ Admin can login and view dashboard
- ✅ Products display with correct pricing and stock
- ✅ Product variants are selectable
- ✅ Customers can add items to cart
- ✅ Orders display with order items and products
- ✅ Coupons apply correctly to orders
- ✅ Wishlist items persist for logged-in users
- ✅ Search functionality works across products
- ✅ Filters work by category and price range
- ✅ Admin can manage orders and view analytics

## Performance Tips

For optimal testing performance:

1. **Use PostgreSQL** (recommended for production-like behavior)
2. **Index frequently queried fields** (already configured)
3. **Clear logs periodically** if disk space is limited
4. **Use database transactions** for API testing:
   ```bash
   php artisan tinker --execute 'DB::beginTransaction();'
   ```

## Next Steps

1. Review the generated data in the database
2. Test API endpoints with sample data
3. Configure any additional settings in `.env`
4. Deploy to staging environment
5. Run final integration tests
6. Prepare for production deployment

---

**Need more help?** Check the [API Documentation](docs/API.md) or [Architecture Guide](docs/ARCHITECTURE.md)
