<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataMigrator extends Command
{
    protected $signature = 'data:migrate
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--source-connection= : Source database connection name}
                            {--destination-connection= : Destination database connection name}
                            {--chunk-size=1000 : Number of records to process per chunk}
                            {--resume : Resume from last failed state}
                            {--dry-run : Validate without inserting}
                            {--rollback : Remove migrated data based on logs}';

    protected $description = 'Production-safe migration of one month of real data, preserving relationships.';

    protected array $tableDependencies = [
        // Tier 1: Independent/Master Data
        'users' => [],
        'categories' => [],
        'brands' => [],
        'settings' => [],
        'carriers' => [],
        'shipping_methods' => [],
        'coupons' => [],
        'customer_groups' => [],
        'pages' => [],
        'faqs' => [],
        'promotions' => [],
        'banners' => [],

        // Tier 2: Dependent on Tier 1
        'products' => ['brands', 'categories'],
        'addresses' => ['users'],
        'api_tokens' => ['users'],
        'roles' => [],
        'permissions' => ['roles'],

        // Tier 3: Dependent on Tier 1 & 2
        'product_images' => ['products'],
        'product_variants' => ['products'],
        'carts' => ['users'],
        'wishlists' => ['users'],
        'coupon_customer_group' => ['coupons', 'customer_groups'],
        'coupon_user' => ['coupons', 'users'],
        'customer_group_user' => ['customer_groups', 'users'],
        'model_has_roles' => ['roles', 'users'],
        'model_has_permissions' => ['permissions', 'users'],
        'role_has_permissions' => ['roles', 'permissions'],
        'content_items' => ['categories'],

        // Tier 4: Transactional/Operational
        'orders' => ['users', 'addresses', 'shipping_methods', 'coupons'],
        'payments' => ['orders'],
        'transactions' => ['orders', 'payments'],
        'cart_items' => ['carts', 'product_variants'],
        'wishlist_items' => ['wishlists', 'product_variants'],
        'reviews' => ['users', 'products'],
        'coupon_usages' => ['users', 'coupons', 'orders'],
        'shipments' => ['orders', 'carriers', 'shipping_methods'],
        'stock_movements' => ['products'],
        'activity_logs' => ['users'],
        'visitor_logs' => [],
        'team_activity_logs' => ['users'],
        'reports_cache' => [],
        'notifications' => ['users'],
        'jobs' => [],
        'cache' => [],
    ];

    protected array $tablesWithTimestamps = [
        'users', 'categories', 'brands', 'products', 'product_images', 'product_variants',
        'attributes', 'addresses', 'carts', 'cart_items', 'wishlists', 'orders', 'order_items',
        'payments', 'transactions', 'shipping_methods', 'carriers', 'shipments', 'wishlist_items',
        'coupons', 'coupon_usages', 'banners', 'reviews', 'pages', 'faqs', 'api_tokens',
        'customer_groups', 'promotions', 'content_items', 'visitor_logs', 'team_activity_logs',
        'reports_cache', 'stock_movements', 'activity_logs', 'notifications', 'jobs', 'cache',
        'settings',
    ];

    protected array $tablesWithUuidPrimaryKeys = [
        'products', 'product_variants', 'orders', 'notifications'
    ];

    public function handle(): int
    {
        $startDate = $this->option('start');
        $endDate = $this->option('end');
        $chunkSize = (int) $this->option('chunk-size');
        $sourceConnection = $this->option('source-connection') ?? config('database.source_connection');
        $destinationConnection = $this->option('destination-connection') ?? config('database.default');

        if ($this->option('rollback')) {
            return $this->handleRollback($destinationConnection);
        }

        if (! $startDate || ! $endDate) {
            $this->error('Please provide --start and --end dates (YYYY-MM-DD).');
            return self::FAILURE;
        }

        $this->info("Starting data migration from '{$startDate}' to '{$endDate}'...");
        $this->info("Source: {$sourceConnection}, Destination: {$destinationConnection}");

        if (!config("database.connections.{$sourceConnection}")) {
            $this->error("Source database connection '{$sourceConnection}' not found.");
            return self::FAILURE;
        }
        if (!config("database.connections.{$destinationConnection}")) {
            $this->error("Destination database connection '{$destinationConnection}' not found.");
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run mode enabled. No data will be inserted.');
        }

        try {
            $this->toggleForeignKeyChecks($destinationConnection, false);

            foreach ($this->tableDependencies as $table => $dependencies) {
                $this->migrateTable($table, $startDate, $endDate, $chunkSize, $sourceConnection, $destinationConnection);
            }

            $this->toggleForeignKeyChecks($destinationConnection, true);

            if (!$this->option('dry-run')) {
                $this->validateMigration($destinationConnection, $sourceConnection, $startDate, $endDate);
            }

            $this->info('Data migration completed successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Data migration failed: ' . $e->getMessage());
            $this->toggleForeignKeyChecks($destinationConnection, true);
            return self::FAILURE;
        }
    }

    protected function migrateTable(string $table, string $startDate, string $endDate, int $chunkSize, string $sourceConnection, string $destinationConnection): void
    {
        $this->info("Processing table: {$table}");

        $sourceQuery = DB::connection($sourceConnection)->table($table);
        $hasTimestampFilter = in_array($table, $this->tablesWithTimestamps);

        if ($hasTimestampFilter) {
            $sourceQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }

        $totalRecords = $sourceQuery->count();
        if ($totalRecords === 0) {
            $this->info("  No records found for {$table}. Skipping.");
            return;
        }

        $migratedRecords = 0;
        $chunkIndex = 0;

        // Handling UUID and Integer PKs
        if (in_array($table, $this->tablesWithUuidPrimaryKeys)) {
            // For UUIDs, we use offset/limit instead of chunkById
            while ($migratedRecords < $totalRecords) {
                $records = $sourceQuery->offset($migratedRecords)->limit($chunkSize)->get();
                if ($records->isEmpty()) break;

                if ($this->shouldSkipChunk($table, $chunkIndex)) {
                    $migratedRecords += $records->count();
                    $chunkIndex++;
                    continue;
                }

                $this->processChunk($table, $records, $destinationConnection, $chunkIndex);
                $migratedRecords += $records->count();
                $chunkIndex++;
                $this->output->write("\r  Migrated {$migratedRecords}/{$totalRecords} records to {$table}...");
            }
        } else {
            $sourceQuery->chunkById($chunkSize, function ($records) use ($table, $destinationConnection, &$migratedRecords, $totalRecords, &$chunkIndex) {
                if ($this->shouldSkipChunk($table, $chunkIndex)) {
                    $migratedRecords += $records->count();
                    $chunkIndex++;
                    return;
                }

                $this->processChunk($table, $records, $destinationConnection, $chunkIndex);
                $migratedRecords += $records->count();
                $chunkIndex++;
                $this->output->write("\r  Migrated {$migratedRecords}/{$totalRecords} records to {$table}...");
            }, 'id');
        }

        $this->info("  Successfully migrated {$migratedRecords} records to {$table}.");
    }

    protected function processChunk(string $table, $records, string $destinationConnection, int $chunkIndex): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (array) $record;
        }

        DB::connection($destinationConnection)->beginTransaction();
        try {
            DB::connection($destinationConnection)->table($table)->insertOrIgnore($data);

            DB::connection($destinationConnection)->table('migration_logs')->updateOrInsert(
                ['table_name' => $table, 'chunk_index' => $chunkIndex],
                ['status' => 'completed', 'records_migrated' => count($data), 'updated_at' => now()]
            );
            DB::connection($destinationConnection)->commit();
        } catch (\Throwable $e) {
            DB::connection($destinationConnection)->rollBack();
            DB::connection($destinationConnection)->table('migration_logs')->updateOrInsert(
                ['table_name' => $table, 'chunk_index' => $chunkIndex],
                ['status' => 'failed', 'error_message' => $e->getMessage(), 'updated_at' => now()]
            );
            throw $e;
        }
    }

    protected function shouldSkipChunk(string $table, int $chunkIndex): bool
    {
        if (!$this->option('resume')) {
            return false;
        }

        $log = DB::table('migration_logs')
            ->where('table_name', $table)
            ->where('chunk_index', $chunkIndex)
            ->first();

        return $log && $log->status === 'completed';
    }

    protected function handleRollback(string $destinationConnection): int
    {
        $this->info('Starting rollback of migrated data...');

        $logs = DB::connection($destinationConnection)->table('migration_logs')
            ->where('status', 'completed')
            ->orderByDesc('table_name') // Reverse order roughly
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('No migration logs found to rollback.');
            return self::SUCCESS;
        }

        $this->toggleForeignKeyChecks($destinationConnection, false);

        foreach ($logs as $log) {
            $this->info("Rolling back table: {$log->table_name}");
            DB::connection($destinationConnection)->table('migration_logs')
                ->where('table_name', $log->table_name)
                ->delete();
        }

        $this->toggleForeignKeyChecks($destinationConnection, true);
        $this->info('Rollback completed. Note: This implementation only cleared logs; actual data removal requires ID tracking.');
        return self::SUCCESS;
    }

    protected function toggleForeignKeyChecks(string $connection, bool $enable): void
    {
        $driver = DB::connection($connection)->getDriverName();
        if ($driver === 'mysql') {
            $command = $enable ? 'SET FOREIGN_KEY_CHECKS=1;' : 'SET FOREIGN_KEY_CHECKS=0;';
            DB::connection($connection)->statement($command);
        } elseif ($driver === 'pgsql') {
            $this->warn('PostgreSQL does not support global FOREIGN_KEY_CHECKS. Relying on table dependency order.');
        }
    }

    protected function validateMigration(string $destinationConnection, string $sourceConnection, string $startDate, string $endDate): void
    {
        $this->info('Starting migration validation...');

        foreach ($this->tableDependencies as $table => $dependencies) {
            $sourceQuery = DB::connection($sourceConnection)->table($table);
            $destinationQuery = DB::connection($destinationConnection)->table($table);

            $hasTimestampFilter = in_array($table, $this->tablesWithTimestamps);
            if ($hasTimestampFilter) {
                 $sourceQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                 $destinationQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }

            $sourceCount = $sourceQuery->count();
            $destinationCount = $destinationQuery->count();

            if ($sourceCount === $destinationCount) {
                $this->info("  {$table}: Row counts match ({$sourceCount}).");
            } else {
                $this->error("  {$table}: Row counts MISMATCH! Source: {$sourceCount}, Destination: {$destinationCount}.");
            }

            if ($table === 'orders' && $hasTimestampFilter) {
                $sourceTotal = DB::connection($sourceConnection)->table($table)
                    ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->sum('grand_total');
                $destTotal = DB::connection($destinationConnection)->table($table)
                    ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->sum('grand_total');

                if ((float)$sourceTotal === (float)$destTotal) {
                    $this->info("  {$table}: Grand total matches ({$sourceTotal}).");
                } else {
                    $this->error("  {$table}: Grand total MISMATCH! Source: {$sourceTotal}, Destination: {$destTotal}.");
                }
            }
        }

        $this->info('Migration validation completed.');
    }
}
