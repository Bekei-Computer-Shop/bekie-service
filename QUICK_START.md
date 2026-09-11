# 🚀 Quick Start Guide - Pre-Production Setup

## 30-Second Setup

```bash
# 1. Install dependencies (first time only)
composer install

# 2. Seed pre-production data
php artisan seed:production --fresh

# 3. Start development server
php artisan serve
```

That's it! Your system is ready for testing.

## 📱 Test Accounts

### Admin Dashboard
- **URL:** `http://localhost:8000/api/admin/docs` (after login)
- **Email:** `admin@example.com`
- **Password:** `password`

### Customer Account
- **Email:** `john.doe@example.com`
- **Password:** `password`

## 🧪 Quick Test

```bash
# 1. Login as customer
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john.doe@example.com","password":"password"}'

# 2. Get your cart
curl http://localhost:8000/api/v1/cart \
  -H "Authorization: Bearer YOUR_TOKEN"

# 3. Add product to cart
curl -X POST http://localhost:8000/api/v1/cart/items \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"product_id":"uuid-here","quantity":1}'
```

## 📊 Data Created

- ✅ **20 Users** (1 admin + 5 customers + 14 test users)
- ✅ **27 Products** with variants
- ✅ **104 Orders** with various statuses
- ✅ **26 Cart Items** across 5 customers
- ✅ **54 Wishlist Items** 
- ✅ **4 Active Coupons**

## 🔄 Reset Everything

```bash
php artisan seed:production --fresh
```

## 📖 Full Documentation

- Setup details: `docs/SETUP_INSTRUCTIONS.md`
- Pre-production guide: `docs/PRE_PRODUCTION_SETUP.md`

## ❓ Issues?

```bash
# Check database connection
php artisan migrate --step

# View recent errors
tail -f storage/logs/laravel.log

# Reset database
php artisan migrate:fresh
```

---

**Ready to code!** 🎉
