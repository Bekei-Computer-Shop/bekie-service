# Historical E-commerce Data Migration - Implementation Guide

## Overview

The historical data migration system moves e-commerce data from a legacy system (2024-01-01 through present day) into the Bekie platform.

**Status**: Production-ready  
**Implementation**: `app/Console/Commands/MigrateHistoricalEcommerceCommand.php`  
**Migration Tracking**: `app/Models/IdMigrationMap.php` + `id_migration_map` table  

---

## Quick Start

### 1. Configure Source Database

Add the legacy database connection to `config/database.php`:

```php
'connections' => [
    'pgsql' => [
        // ... existing config
    ],
    
    'legacy-pgsql' => [
        'driver' => 'pgsql',
        'host' => env('LEGACY_DB_HOST'),
        'port' => env('LEGACY_DB_PORT', 5432),
        'database' => env('LEGACY_DB_DATABASE'),
        'username' => env('LEGACY_DB_USERNAME'),
        'password' => env('LEGACY_DB_PASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
        'sslmode' => env('LEGACY_DB_SSLMODE', 'disable'),
    ],
],
```

### 2. Set Environment Variables

Add to `.env`:

```bash
LEGACY_DB_HOST=your-legacy-db-host
LEGACY_DB_PORT=5432
LEGACY_DB_DATABASE=legacy_ecommerce
LEGACY_DB_USERNAME=postgres
LEGACY_DB_PASSWORD=your-password
LEGACY_DB_SSLMODE=disable
```

### 3. Run Migration Table Setup

```bash
php artisan migrate
```

This creates the `id_migration_map` table for tracking source→target ID mappings.

### 4. Test Connection

```bash
php artisan data:migrate-ecommerce --dry-run
```

This validates connectivity and shows what would be migrated without inserting data.

### 5. Execute Migration

```bash
php artisan data:migrate-ecommerce
```

Default range: 2024-01-01 to today.

---

## Command Syntax

```bash
php artisan data:migrate-ecommerce [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--start=YYYY-MM-DD` | 2024-01-01 | Migration start date |
| `--end=YYYY-MM-DD` | Today | Migration end date |
| `--source=pgsql` | pgsql | Source database connection name |
| `--chunk=2000` | 2000 | Records per batch |
| `--phase=N` | All | Run specific phase only (1-8) |
| `--uuid-strategy=deterministic` | deterministic | UUID generation (deterministic or sequential) |
| `--dry-run` | - | Preview without inserting |
| `--skip-validation` | - | Skip pre/post validation |
| `--verbose` | - | Detailed output |

### Examples

**Dry-run preview:**
```bash
php artisan data:migrate-ecommerce --dry-run
```

**Migrate with custom date range:**
```bash
php artisan data:migrate-ecommerce --start=2024-06-01 --end=2024-12-31
```

**Migrate products only (Phase 3):**
```bash
php artisan data:migrate-ecommerce --phase=3
```

**Migrate with sequential UUIDs:**
```bash
php artisan data:migrate-ecommerce --uuid-strategy=sequential
```

**Migrate with smaller batches:**
```bash
php artisan data:migrate-ecommerce --chunk=500 --verbose
```

---

## Migration Phases

The system migrates in 8 phases respecting foreign key dependencies.

### Phase 1: Reference & Master Data
Tables: `permissions`, `roles`, `role_has_permissions`, `customer_groups`

**Purpose**: System reference data required for all other phases.  
**Dependencies**: None

### Phase 2: User & Address Data
Tables: `users`, `addresses`, `api_tokens`, `model_has_roles`

**Purpose**: Customer accounts and profiles.  
**Dependencies**: Phase 1 (roles)

### Phase 3: Product Catalog
Tables: `categories`, `brands`, `products`, `product_images`, `product_variants`, `attributes`, `stock_movements`

**Purpose**: Product master data (UUID handling critical here).  
**Dependencies**: None

### Phase 4: Shipping Infrastructure
Tables: `shipping_methods`, `carriers`, `promotions`

**Purpose**: Order fulfillment options.  
**Dependencies**: None

### Phase 5: Transactional Data
Tables: `orders`, `order_items`, `payments`, `shipments`, `transactions`

**Purpose**: Customer orders and fulfillment.  
**Dependencies**: Phase 2 (users), Phase 3 (products), Phase 4 (shipping methods)

### Phase 6: Customer Engagement
Tables: `carts`, `cart_items`, `wishlists`, `wishlist_items`, `reviews`, `coupon_usages`

**Purpose**: Customer interaction history.  
**Dependencies**: Phase 2 (users), Phase 3 (products), Phase 5 (orders)

### Phase 7: Coupon Data
Tables: `coupons`, `coupon_product`, `coupon_customer_group`

**Purpose**: Promotional data.  
**Dependencies**: Phase 2 (customer groups, users)

### Phase 8: Content & Settings
Tables: `content_items`, `faqs`, `banners`, `settings`, `notifications`, `activity_logs`

**Purpose**: Marketing and administrative data.  
**Dependencies**: Phase 2 (users), Phase 3 (categories)

---

## ID Preservation Strategy

### Products (UUID Primary Key)

The migration supports two UUID strategies:

**Option 1: Deterministic (Recommended)**

```bash
php artisan data:migrate-ecommerce --uuid-strategy=deterministic
```

- Generates predictable UUIDs based on source product ID
- Formula: `hash('sha256', "products-{source_id}")`
- **Pros**: Repeatable, auditable, no lookup table needed
- **Cons**: Non-sequential UUIDs
- **Use when**: External systems reference product IDs and need auditable mapping

**Option 2: Sequential**

```bash
php artisan data:migrate-ecommerce --uuid-strategy=sequential
```

- Generates new random UUIDs for each product
- Records mapping in `id_migration_map` table
- **Pros**: Sequential, normal UUID appearance
- **Cons**: Requires lookup table for historical reference
- **Use when**: Only internal system needs product references

### Orders (Integer Primary Key)

Orders retain their sequential integer IDs from source system.

Mapping tracked in `id_migration_map`:
```php
IdMigrationMap::mapSourceToTarget('orders', $sourceOrderId, $targetOrderId);
```

### Other Tables

Standard integer primary keys preserved where possible, auto-increment where conflicts detected.

---

## Idempotency & Re-running

The migration is **idempotent** and safe to re-run.

### Duplicate Detection Strategy

Each table uses natural unique keys to avoid duplicates:

| Table | Unique Key |
|-------|-----------|
| `users` | `email` |
| `products` | `sku` |
| `orders` | `order_number` |
| `coupons` | `code` |
| `reviews` | `user_id, product_id, order_id` |
| `addresses` | `user_id, phone` |
| `shipments` | `order_id` |

Uses `updateOrInsert()` to upsert records.

### Safe Re-run

```bash
# Migrate data
php artisan data:migrate-ecommerce

# ... later, re-run to get any new data ...
php artisan data:migrate-ecommerce --start=2024-01-01 --end=2026-09-11

# No duplicates, updated records merged
```

### Migration Tracking

All migrated records tagged with:
- `migration_batch = 'historical-2024-2026'`
- `migrated_at = now()`

Query migrated records:
```php
Product::where('migration_batch', 'historical-2024-2026')->count();
```

---

## Dry-Run Mode

Preview migration without inserting data:

```bash
php artisan data:migrate-ecommerce --dry-run --verbose
```

Output:
- Validates source database connection
- Validates target schema exists
- Counts records that would be migrated per table
- Identifies potential conflicts
- No data written to either database

---

## Validation

### Pre-Migration Checks

Run automatically unless `--skip-validation` is passed:

✅ Source database connection  
✅ All target tables exist  
✅ Source database has required tables  
✅ Date range validity  

### Post-Migration Checks

Validates critical tables:

| Table | Check |
|-------|-------|
| `users` | Row count source ↔ target |
| `products` | Row count source ↔ target |
| `orders` | Row count + grand_total sum |
| Others | Basic count verification |

Example output:
```
Post-migration validation:
  ✅ users: source=500, target=500
  ✅ products: source=2500, target=2500
  ✅ orders: source=10000, target=10000
```

---

## Error Handling

### Common Issues

**"Source database connection not configured"**

Solution: Add connection to `config/database.php` and set .env variables.

**"Target table does not exist"**

Solution: Run migrations: `php artisan migrate`

**"FOREIGN_KEY_CHECKS error"**

Solution: Phase order respects dependencies. Manual soft-delete records if needed.

**"Duplicate key constraint violation"**

Solution: Check natural key uniqueness before migration. Use `--dry-run` first.

### Failure Recovery

If migration fails mid-run:

1. Check error message for specific table/phase
2. Fix the underlying issue (unique constraint, missing FK, schema mismatch)
3. Re-run with `--phase=N` to restart from that phase
4. Migration is idempotent - safe to re-run

Example:
```bash
# Fails at Phase 5
php artisan data:migrate-ecommerce

# Fix foreign key issue
# Re-run just Phase 5
php artisan data:migrate-ecommerce --phase=5
```

---

## Performance Tuning

### Batch Size

Default chunk size: **2000 records**

Adjust based on your database performance:

```bash
# Larger batches (faster, more memory, higher lock time)
php artisan data:migrate-ecommerce --chunk=5000

# Smaller batches (slower, less memory, shorter locks)
php artisan data:migrate-ecommerce --chunk=500
```

**Recommended values**:
- **Large tables** (activity_logs, order_items): `5000-10000`
- **Medium tables** (orders, products): `2000-5000`
- **Small tables** (permissions, roles): `1000-2000`

### Foreign Key Checks

During migration (unless `--dry-run`):

PostgreSQL: `SET session_replication_role = replica;`  
MySQL: `SET FOREIGN_KEY_CHECKS=0;`

Re-enabled after migration completes or on error.

### Date Filtering

Migration filters by `created_at`:

```sql
WHERE created_at BETWEEN '2024-01-01 00:00:00' AND '2026-09-11 23:59:59'
```

Soft-deleted records automatically excluded:
```sql
AND deleted_at IS NULL
```

---

## Production Deployment

### Pre-Deployment Checklist

- [ ] Source database connection configured and tested
- [ ] Target database backed up
- [ ] Schedule during maintenance window
- [ ] Notify users of data migration
- [ ] Run `--dry-run` first
- [ ] Review validation output
- [ ] Have rollback plan ready (DB snapshot)

### Deployment Steps

1. **Enable maintenance mode** (optional):
   ```bash
   php artisan down
   ```

2. **Create backup**:
   ```bash
   # PostgreSQL
   pg_dump bekie_service > backup-2026-09-11.sql
   ```

3. **Run migration**:
   ```bash
   php artisan data:migrate-ecommerce \
     --start=2024-01-01 \
     --end=2026-09-11 \
     --verbose
   ```

4. **Verify migration**:
   ```bash
   php artisan data:migrate-ecommerce --dry-run
   # Compare counts
   ```

5. **Disable maintenance mode**:
   ```bash
   php artisan up
   ```

### Rollback Plan

If migration fails critically:

```bash
# PostgreSQL restore
psql bekie_service < backup-2026-09-11.sql

# OR delete migrated records
DELETE FROM id_migration_map;
DELETE FROM users WHERE migration_batch = 'historical-2024-2026';
DELETE FROM products WHERE migration_batch = 'historical-2024-2026';
# ... etc for all tables
```

---

## Monitoring & Logs

### Command Output

The command provides real-time progress:

```
PHASE 5: Transactional Data (Orders)
  Processed: 1500/10000
  ✅ 1500 migrated
```

### Laravel Logs

Migration events logged to `storage/logs/laravel.log`:

Enable verbose output for debugging:
```bash
php artisan data:migrate-ecommerce --verbose
```

### Database Queries

Monitor active migrations with:

PostgreSQL:
```sql
SELECT * FROM pg_stat_activity WHERE state = 'active';
```

MySQL:
```sql
SHOW PROCESSLIST;
```

---

## API Usage

Use `IdMigrationMap` model to look up source→target IDs:

```php
use App\Models\IdMigrationMap;

// Get target UUID for source product ID
$targetUuid = IdMigrationMap::mapSourceToTarget('products', 12345);

// Record a new mapping
IdMigrationMap::recordMapping(
    table: 'orders',
    sourceId: 'ORDER-123',
    targetId: 456,
    sourceType: 'string',
    targetType: 'integer',
    metadata: ['batch' => 'historical-2024-2026']
);
```

---

## Troubleshooting

### Migration Taking Too Long

- Reduce batch size: `--chunk=500`
- Run specific phase: `--phase=3` (products)
- Check database indexes exist
- Monitor with: `SHOW PROCESSLIST;`

### High Memory Usage

- Reduce batch size: `--chunk=100`
- Run phase-by-phase
- Monitor with: `top` or Task Manager

### Connection Timeouts

- Increase DB connection timeout in `config/database.php`
- Check network connectivity
- Verify firewall rules

### UUID Strategy Confusion

**Use Deterministic if**:
- External APIs reference product IDs
- Need audit trail of mappings
- Repeatable/idempotent behavior important

**Use Sequential if**:
- Internal-only system
- Clean slate migration preferred
- UUID collisions less critical

---

## Support & Questions

For questions about the migration:

1. Check this guide's Troubleshooting section
2. Run `php artisan data:migrate-ecommerce --help`
3. Review `docs/HISTORICAL_DATA_MIGRATION_PLAN.md` for architecture
4. Check `storage/logs/laravel.log` for detailed errors

---

**Last Updated**: 2026-09-11  
**Version**: 1.0  
**Status**: Production-ready
