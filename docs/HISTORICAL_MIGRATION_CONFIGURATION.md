# Historical Data Migration - Configuration Guide

## Prerequisites

- Laravel 12+ installed
- PostgreSQL (or MySQL/SQLite) for both source and target databases
- Database credentials for legacy e-commerce system
- Sufficient disk space for large data transfers

---

## Configuration Steps

### Step 1: Add Database Connection

Edit `config/database.php` and add the legacy database connection:

```php
'connections' => [
    // ... existing connections ...
    
    'legacy-pgsql' => [
        'driver' => 'pgsql',
        'host' => env('LEGACY_DB_HOST'),
        'port' => env('LEGACY_DB_PORT', 5432),
        'database' => env('LEGACY_DB_DATABASE'),
        'username' => env('LEGACY_DB_USERNAME'),
        'password' => env('LEGACY_DB_PASSWORD'),
        'charset' => env('DB_CHARSET', 'utf8'),
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => env('LEGACY_DB_SSLMODE', 'disable'),
    ],
],
```

**Note**: The template is already in the project. Just configure the environment variables.

### Step 2: Set Environment Variables

Add to `.env`:

```bash
# Legacy Database Connection (for historical data migration)
LEGACY_DB_HOST=your-legacy-db-host.example.com
LEGACY_DB_PORT=5432
LEGACY_DB_DATABASE=legacy_ecommerce_db
LEGACY_DB_USERNAME=postgres
LEGACY_DB_PASSWORD=your-secure-password
LEGACY_DB_SSLMODE=disable  # Use 'require' for remote databases
```

### Step 3: Test Connection

Verify the configuration is working:

```bash
# This will validate the source database connection
php artisan data:migrate-ecommerce --dry-run
```

Expected output:
```
✅ Source database connection verified
✅ All target tables exist
✅ Found X source tables
```

If validation fails, check:
- Database host is accessible
- Credentials are correct
- Database exists
- Network/firewall rules allow connection
- Port 5432 (PostgreSQL) is open

### Step 4: Run Migration

Once validation passes:

```bash
php artisan data:migrate-ecommerce
```

---

## Environment Variable Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `LEGACY_DB_HOST` | Yes | - | Hostname/IP of legacy database |
| `LEGACY_DB_PORT` | No | 5432 | Database port |
| `LEGACY_DB_DATABASE` | Yes | - | Database name |
| `LEGACY_DB_USERNAME` | Yes | - | Database user |
| `LEGACY_DB_PASSWORD` | Yes | - | Database password |
| `LEGACY_DB_SSLMODE` | No | disable | SSL mode: disable, allow, prefer, require |

---

## Database Connection Security

### Local Development

For local/staging databases:
```bash
LEGACY_DB_HOST=localhost
LEGACY_DB_SSLMODE=disable
```

### Remote Production Database

For secure remote connections:
```bash
LEGACY_DB_HOST=db.example.com
LEGACY_DB_SSLMODE=require
LEGACY_DB_PASSWORD=your-complex-password
```

### SSH Tunnel (if database not directly accessible)

If the legacy database is behind a firewall, use SSH tunneling:

```bash
# Create SSH tunnel
ssh -L 5433:legacy-db-host:5432 user@bastion-host -N

# Then configure
LEGACY_DB_HOST=localhost
LEGACY_DB_PORT=5433
LEGACY_DB_SSLMODE=disable
```

---

## Database User Permissions

The migration user should have:

```sql
-- Minimal required permissions
GRANT CONNECT ON DATABASE legacy_ecommerce_db TO migration_user;
GRANT USAGE ON SCHEMA public TO migration_user;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO migration_user;
```

**Notes**:
- Only `SELECT` permission needed (read-only)
- No write/delete permissions required
- Source database remains untouched

---

## Connection Troubleshooting

### "could not translate host name"

**Problem**: DNS resolution failed

**Solution**:
```bash
# Verify host is reachable
ping your-legacy-db-host.example.com

# Test port connectivity
telnet your-legacy-db-host.example.com 5432
```

### "connection refused"

**Problem**: Cannot connect to the port

**Solution**:
- Verify database is running
- Check port number is correct (default: 5432)
- Verify firewall allows connection
- Check database is listening on the correct interface

```sql
-- In PostgreSQL
SHOW listen_addresses;  -- Should not be 'localhost' if connecting remotely
```

### "password authentication failed"

**Problem**: Credentials are incorrect

**Solution**:
- Verify username and password in `.env`
- Test credentials manually:
  ```bash
  psql -h your-host -U your-user -d your-database
  ```
- Check for special characters in password (escape if needed)

### "permission denied"

**Problem**: User lacks required permissions

**Solution**:
- Ask database administrator to grant SELECT on all tables
- Verify user can connect and read tables:
  ```bash
  psql -c "SELECT COUNT(*) FROM users;" -h $LEGACY_DB_HOST -U $LEGACY_DB_USERNAME -d $LEGACY_DB_DATABASE
  ```

---

## Performance Configuration

### For Large Databases (>1GB)

Adjust chunk size and connection parameters:

```bash
# config/database.php
'legacy-pgsql' => [
    // ... existing config ...
    'options' => [
        'statement_timeout' => 300000,  // 5 minutes per query
        'search_path' => 'public',
    ],
]

# .env
LEGACY_DB_CONNECTION_TIMEOUT=10
```

### Command Options for Performance

```bash
# Smaller batches for limited memory
php artisan data:migrate-ecommerce --chunk=500

# Larger batches for better throughput
php artisan data:migrate-ecommerce --chunk=10000
```

---

## Network Configuration

### Direct Connection

```
Your App ──→ Legacy Database
```

Most common for co-located databases.

### Through SSH Bastion/Jump Host

```
Your App ──→ SSH Tunnel ──→ Bastion Host ──→ Legacy Database
```

Set up tunnel:
```bash
ssh -L 5433:legacy-db:5432 bastion-user@bastion-host -N -f
```

### Through VPN

If on a VPN:
```bash
# Ensure VPN is connected
# Then use internal IP/hostname
LEGACY_DB_HOST=10.0.0.100
```

---

## Multi-Database Setups

### Scenario: Different Source & Target DB Systems

If migrating from MySQL to PostgreSQL:

```php
// config/database.php
'legacy-mysql' => [
    'driver' => 'mysql',
    'host' => env('LEGACY_DB_HOST'),
    'port' => env('LEGACY_DB_PORT', 3306),
    'database' => env('LEGACY_DB_DATABASE'),
    'username' => env('LEGACY_DB_USERNAME'),
    'password' => env('LEGACY_DB_PASSWORD'),
],
```

Then run:
```bash
php artisan data:migrate-ecommerce --source=legacy-mysql
```

---

## Verification After Configuration

### Quick Verification

```bash
php artisan data:migrate-ecommerce --dry-run --verbose
```

### Full Pre-flight Check

```bash
# 1. Test connection
php artisan data:migrate-ecommerce --dry-run

# 2. Check table counts
php artisan tinker
> DB::connection('legacy-pgsql')->table('users')->count()
> DB::connection('legacy-pgsql')->table('products')->count()

# 3. Sample record structure
> DB::connection('legacy-pgsql')->table('users')->first()
```

### Before Running Full Migration

1. ✅ Connection test passes
2. ✅ Can access all required tables
3. ✅ Target database is empty or backed up
4. ✅ Have tested `--dry-run` successfully
5. ✅ Backup of legacy database exists
6. ✅ Maintenance window scheduled

---

## Production Deployment Checklist

- [ ] Source database credentials added to `.env`
- [ ] Database connection tested with `--dry-run`
- [ ] Target database backed up
- [ ] Maintenance window scheduled
- [ ] Team notified of migration
- [ ] Production `.env` has correct legacy DB credentials
- [ ] Migration command tested in staging first
- [ ] Rollback plan documented
- [ ] Post-migration validation plan ready

---

## Support

For configuration issues:
1. Check `storage/logs/laravel.log` for detailed error messages
2. Run with `--verbose` flag for more output
3. Test connection manually with `psql` or equivalent
4. Verify environment variables with `php artisan config:show database.connections.legacy-pgsql`

---

**Last Updated**: 2026-09-11  
**Status**: Production Ready
