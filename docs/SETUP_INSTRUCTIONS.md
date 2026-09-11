# Pre-Production Environment Setup

## 🚀 Quick Start

### Fresh Setup (First Time)
```bash
# Navigate to project directory
cd bekie-service

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_DATABASE=bekie_service
# etc.

# Seed pre-production data
php artisan seed:production --fresh
```

### Existing Database
```bash
php artisan seed:production
```

## 📊 What Gets Created

When you run the pre-production seeder, your database will be populated with:

### Users (20 total)
- **1 Admin** (admin@example.com)
- **5 Sample Customers** with realistic profiles
- **14 Additional Customers** for testing

### Products (27 total)
- High-end electronics (MacBooks, iPhones, iPads)
- Premium audio equipment
- Fashion items (clothing, blazers)
- Home & garden products
- Sports & outdoor gear

### Product Variants (56 total)
- Multiple storage options
- Various colors and sizes
- Price variations per variant

### Orders (104 total)
- Various order statuses: pending, processing, shipped, delivered, cancelled
- Multiple payment statuses: pending, paid, failed, refunded
- Realistic order items with snapshots
- Different discount amounts applied

### Shopping Carts (5 total)
- 1 per sample customer
- Multiple items in each cart with realistic pricing

### Wishlists (5 total)
- 1 per sample customer  
- Multiple products saved in each wishlist

### Coupons (4+ total)
- WELCOME10: 10% off (min $50)
- SAVE20: 20% off (min $100)
- FLAT15: $15 fixed discount (min $75)
- SUMMER30: 30% off (min $150)

## 🔐 Default Credentials

### Admin Panel Access
```
Email:    admin@example.com
Password: password
```

### Sample Customer Accounts
```
Email:    john.doe@example.com      | Password: password
Email:    jane.smith@example.com    | Password: password
Email:    michael.johnson@example.com | Password: password
Email:    sarah.williams@example.com | Password: password
Email:    robert.brown@example.com  | Password: password
```

## 🌐 API Endpoints to Test

### Public API (Customer)
```bash
# Get all products
GET /api/v1/products

# Get featured products
GET /api/v1/products?featured=1

# Get product details
GET /api/v1/products/{id}

# Get categories
GET /api/v1/categories

# Login
POST /api/v1/auth/login

# View cart
GET /api/v1/cart

# Create order
POST /api/v1/orders
```

### Admin API
```bash
# Login as admin
POST /api/v1/admin/auth/login

# View all orders
GET /api/v1/admin/orders

# View all products
GET /api/v1/admin/products

# View all customers
GET /api/v1/admin/customers

# View dashboard
GET /api/v1/admin/dashboard
```

## 📋 Testing Workflows

### Customer Journey
1. Browse products: `GET /api/v1/products`
2. View product details: `GET /api/v1/products/{id}`
3. Add to cart: `POST /api/v1/cart/items`
4. View cart: `GET /api/v1/cart`
5. Apply coupon: `POST /api/v1/cart/coupons`
6. Checkout: `POST /api/v1/orders`

### Admin Workflow
1. Login: `POST /api/v1/admin/auth/login`
2. View orders: `GET /api/v1/admin/orders`
3. Update order status: `PUT /api/v1/admin/orders/{id}`
4. Manage products: `GET/POST/PUT /api/v1/admin/products`
5. View analytics: `GET /api/v1/admin/dashboard`

## 🛠️ Development Server

### Start Development Environment
```bash
# Option 1: Run all services
composer run dev

# Option 2: Run services individually
php artisan serve                    # API server on :8000
php artisan queue:work              # Queue worker
npm run dev                         # Vite for assets (if applicable)
```

### Run Tests
```bash
# Run all tests
composer test

# Run specific test
php artisan test --filter=OrderTest

# Run with coverage
php artisan test --coverage
```

## 📝 Environment Configuration

### Database Setup
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bekie_service
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### Queue Configuration (Optional)
```env
QUEUE_CONNECTION=sync        # For development
# or
QUEUE_CONNECTION=redis       # For production-like testing
```

### Mail Configuration (Optional)
```env
MAIL_MAILER=log             # Logs emails instead of sending
MAIL_FROM_ADDRESS=noreply@bekie.local
```

## 🔄 Reset Database

### Keep Data and Resync
```bash
php artisan migrate:rollback
php artisan migrate
```

### Fresh Start (Wipe Everything)
```bash
php artisan seed:production --fresh
```

### Specific Seeder
```bash
php artisan db:seed --class=PreProductionSeeder
```

## 📊 Database Statistics

After seeding, query counts:

```bash
# Users
SELECT COUNT(*) FROM users;  -- Should be ~20

# Products
SELECT COUNT(*) FROM products;  -- Should be ~27

# Orders
SELECT COUNT(*) FROM orders;  -- Should be ~104

# Total Revenue (sample calculation)
SELECT SUM(grand_total) as total_revenue FROM orders WHERE payment_status = 'paid';
```

## 🚨 Troubleshooting

### Foreign Key Errors
```bash
# Ensure migrations ran
php artisan migrate

# Check foreign key constraints
php artisan db:show
```

### Duplicate Entry Errors
```bash
# The seeder uses firstOrCreate, so this shouldn't happen
# If it does, verify database isn't corrupted
php artisan seed:production --fresh
```

### Memory Limit Issues
```bash
# Increase PHP memory for seeding
php -d memory_limit=512M artisan seed:production
```

### Connection Issues
- Verify PostgreSQL is running
- Check database credentials in `.env`
- Ensure database exists: `createdb bekie_service`

## 📚 Related Documentation

- [API Documentation](../openapi.json)
- [Architecture Guide](ARCHITECTURE.md)
- [Database Schema](../database/schema.sql)
- [Environment Setup](../README.md)

## 🎯 Next Steps

1. ✅ Run pre-production seeder
2. ✅ Test customer workflow
3. ✅ Test admin workflow
4. ✅ Verify API responses
5. ✅ Check frontend integration
6. ✅ Run full test suite
7. ✅ Deploy to staging

---

**Questions?** Check the logs:
```bash
tail -f storage/logs/laravel.log
```
