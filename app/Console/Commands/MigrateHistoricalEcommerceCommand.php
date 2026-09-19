<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateHistoricalEcommerceCommand extends Command
{
    protected $signature = 'data:migrate-ecommerce
                            {--source=pgsql : Source database connection name}
                            {--start=2024-01-01 : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD), default: now()}
                            {--chunk=2000 : Chunk size for batch processing}
                            {--phase= : Run specific phase only (1-8)}
                            {--skip-validation : Skip pre/post migration validation}
                            {--dry-run : Validate without inserting}
                            {--uuid-strategy=deterministic : UUID strategy (deterministic or sequential)}';

    protected $description = 'Migrate historical e-commerce data (2024-01-01 to present)';

    protected array $phases = [
        1 => [
            'name' => 'Reference & Master Data',
            'tables' => ['permissions', 'roles', 'role_has_permissions', 'customer_groups'],
        ],
        2 => [
            'name' => 'User & Address Data',
            'tables' => ['users', 'addresses', 'api_tokens', 'model_has_roles'],
        ],
        3 => [
            'name' => 'Product Catalog',
            'tables' => ['categories', 'brands', 'products', 'product_images', 'product_variants', 'attributes', 'stock_movements'],
        ],
        4 => [
            'name' => 'Shipping Infrastructure',
            'tables' => ['shipping_methods', 'carriers', 'promotions'],
        ],
        5 => [
            'name' => 'Transactional Data (Orders)',
            'tables' => ['orders', 'order_items', 'payments', 'shipments', 'transactions'],
        ],
        6 => [
            'name' => 'Customer Engagement',
            'tables' => ['carts', 'cart_items', 'wishlists', 'wishlist_items', 'reviews', 'coupon_usages'],
        ],
        7 => [
            'name' => 'Coupon Data',
            'tables' => ['coupons', 'coupon_product', 'coupon_customer_group'],
        ],
        8 => [
            'name' => 'Content & Settings',
            'tables' => ['content_items', 'faqs', 'banners', 'settings', 'notifications', 'activity_logs'],
        ],
    ];

    protected array $uuidTables = ['products', 'product_variants', 'orders', 'notifications'];

    protected array $softDeleteTables = [
        'users', 'products', 'orders', 'payments', 'shipments', 'reviews',
        'coupons', 'wishlists', 'carts', 'addresses', 'product_images',
        'content_items', 'banners', 'stock_movements',
    ];

    protected array $tablesWithoutTimestamps = [
        'role_has_permissions', 'model_has_roles', 'coupon_product',
        'coupon_customer_group', 'coupon_user', 'customer_group_user',
    ];

    protected array $specialDateColumns = [
        'orders' => ['paid_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'refunded_at'],
        'coupons' => ['starts_at', 'expires_at'],
        'carts' => ['expires_at', 'last_activity_at'],
        'shipments' => ['packed_at', 'shipped_at', 'delivered_at', 'failed_at', 'returned_at'],
    ];

    protected string $migrationBatch = 'historical-2024-2026';

    protected array $stats = ['total' => 0, 'migrated' => 0, 'skipped' => 0, 'failed' => 0];

    public function handle(): int
    {
        try {
            $this->outputHeading('Historical E-commerce Data Migration');

            // Parse and validate options
            $startDate = $this->option('start');
            $endDate = $this->option('end') ?: now()->format('Y-m-d');
            $sourceConnection = $this->option('source');
            $chunkSize = (int) $this->option('chunk');
            $phase = $this->option('phase') ? (int) $this->option('phase') : null;

            // Validate dates
            if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate)) {
                $this->error('Invalid date format. Use YYYY-MM-DD.');

                return self::FAILURE;
            }

            // Validate source database exists
            if (! $this->validateSourceDatabase($sourceConnection)) {
                return self::FAILURE;
            }

            // Run pre-migration validation
            if (! $this->option('skip-validation')) {
                if (! $this->runPreMigrationValidation($sourceConnection, $startDate, $endDate)) {
                    return self::FAILURE;
                }
            }

            $this->outputInfo("Date Range: {$startDate} → {$endDate}");
            $this->outputInfo("Chunk Size: {$chunkSize}");
            $this->outputInfo("UUID Strategy: {$this->option('uuid-strategy')}");
            $this->outputInfo('Dry Run: '.($this->option('dry-run') ? 'YES' : 'NO'));
            $this->line('');

            // Disable foreign key checks
            if (! $this->option('dry-run')) {
                $this->disableForeignKeyChecks();
            }

            try {
                // Run specified phase or all phases
                $phasesToRun = $phase ? [$phase => $this->phases[$phase]] : $this->phases;

                foreach ($phasesToRun as $phaseNumber => $phaseConfig) {
                    if ($this->option('dry-run')) {
                        $this->outputPhase($phaseNumber, $phaseConfig, 'DRY RUN');
                    } else {
                        $this->outputPhase($phaseNumber, $phaseConfig);
                    }

                    foreach ($phaseConfig['tables'] as $table) {
                        $this->migrateTable(
                            $table,
                            $sourceConnection,
                            $startDate,
                            $endDate,
                            $chunkSize
                        );
                    }
                }

                // Re-enable foreign key checks
                if (! $this->option('dry-run')) {
                    $this->enableForeignKeyChecks();
                }

                // Run post-migration validation
                if (! $this->option('dry-run') && ! $this->option('skip-validation')) {
                    $this->runPostMigrationValidation($sourceConnection, $startDate, $endDate);
                }

                // Output summary
                $this->outputSummary();

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->enableForeignKeyChecks();
                $this->error('Migration failed: '.$e->getMessage());
                if ($this->option('verbose')) {
                    $this->error($e->getTraceAsString());
                }

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Fatal error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function migrateTable(
        string $table,
        string $sourceConnection,
        string $startDate,
        string $endDate,
        int $chunkSize
    ): void {
        try {
            $sourceQuery = DB::connection($sourceConnection)->table($table);

            // Apply date filter (only on created_at, skip for tables without timestamps)
            if (! in_array($table, $this->tablesWithoutTimestamps)) {
                $sourceQuery->whereBetween('created_at', [
                    Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay(),
                ]);

                // Filter out soft-deleted records if table has deleted_at column
                if (in_array($table, $this->softDeleteTables) && $this->tableHasColumn($sourceConnection, $table, 'deleted_at')) {
                    $sourceQuery->whereNull('deleted_at');
                }
            }

            $totalRecords = $sourceQuery->count();
            if ($totalRecords === 0) {
                $this->info('  ⊘ No records to migrate');

                return;
            }

            $this->stats['total'] += $totalRecords;
            $migratedCount = 0;
            $failedCount = 0;
            $chunkIndex = 0;

            // Check if table has an 'id' column for chunkById
            $hasIdColumn = $this->tableHasColumn($sourceConnection, $table, 'id');

            // Process in chunks using offset/limit for pivot tables or chunkById for others
            if ($hasIdColumn) {
                $sourceQuery->orderBy('id')->chunkById($chunkSize, function ($records) use (
                    $table,
                    &$migratedCount,
                    &$failedCount,
                    &$chunkIndex,
                    $totalRecords
                ) {
                    $this->processChunk($records, $table, $totalRecords, $migratedCount, $failedCount, $chunkIndex);
                });
            } else {
                // Use offset/limit for tables without id column (pivot tables)
                $offset = 0;
                while ($offset < $totalRecords) {
                    $records = $sourceQuery->offset($offset)->limit($chunkSize)->get();
                    if ($records->isEmpty()) {
                        break;
                    }
                    $this->processChunk($records, $table, $totalRecords, $migratedCount, $failedCount, $chunkIndex);
                    $offset += $chunkSize;
                }
            }

            $this->line('');
            $this->info("  ✅ {$migratedCount} migrated".($failedCount > 0 ? ", {$failedCount} failed" : ''));
        } catch (\Throwable $e) {
            $this->error("  ❌ Error migrating {$table}: ".$e->getMessage());
            $this->stats['failed'] += $totalRecords ?? 0;
        }
    }

    protected function transformRecord(string $table, array $record): array
    {
        // Handle UUID tables
        if (in_array($table, $this->uuidTables) && isset($record['id']) && ! Str::isUuid($record['id'])) {
            $record['id'] = $this->generateOrMapUuid($table, $record['id']);
        }

        return $record;
    }

    protected function generateOrMapUuid(string $table, string $sourceId): string
    {
        if ($this->option('uuid-strategy') === 'deterministic') {
            // Deterministic UUID based on source ID
            $hash = hash('sha256', "{$table}-{$sourceId}", false);
            $hex = substr($hash, 0, 32);

            // Convert hex to UUID v4-like format
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );
        } else {
            // Sequential UUID - generate new
            return Str::uuid();
        }
    }

    protected function getUniqueKeyForTable(string $table, array $record): array
    {
        // Return unique identifiers for updateOrInsert
        $uniqueKeys = [
            'users' => ['email'],
            'products' => ['sku'],
            'orders' => ['order_number'],
            'coupons' => ['code'],
            'reviews' => ['user_id', 'product_id', 'order_id'],
            'addresses' => ['user_id', 'phone'],
            'shipments' => ['order_id'],
        ];

        if (isset($uniqueKeys[$table])) {
            $key = [];
            foreach ($uniqueKeys[$table] as $field) {
                if (isset($record[$field])) {
                    $key[$field] = $record[$field];
                }
            }

            return $key ?: ['id' => $record['id'] ?? null];
        }

        return ['id' => $record['id'] ?? null];
    }

    protected function processChunk(
        $records,
        string $table,
        int $totalRecords,
        int &$migratedCount,
        int &$failedCount,
        int &$chunkIndex
    ): void {
        try {
            if ($this->option('dry-run')) {
                // Just count records, don't insert
                $migratedCount += $records->count();
            } else {
                $recordsToInsert = $records->map(function ($record) use ($table) {
                    return $this->transformRecord($table, (array) $record);
                })->toArray();

                // Insert with idempotency check
                foreach ($recordsToInsert as $record) {
                    try {
                        DB::table($table)->updateOrInsert(
                            $this->getUniqueKeyForTable($table, $record),
                            $record
                        );
                        $migratedCount++;
                        $this->stats['migrated']++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->stats['failed']++;
                        if ($this->option('verbose')) {
                            $this->warn("  Failed to insert record in {$table}: ".$e->getMessage());
                        }
                    }
                }
            }

            // Progress output
            $progress = min($migratedCount, $totalRecords);
            $this->output->write("\r  Processed: {$progress}/{$totalRecords}");
            $chunkIndex++;
        } catch (\Throwable $e) {
            $failedCount += $records->count();
            $this->stats['failed'] += $records->count();
            $this->warn('  Chunk processing failed: '.$e->getMessage());
        }
    }

    protected function tableHasColumn(string $connection, string $table, string $column): bool
    {
        try {
            $schema = DB::connection($connection)->getSchemaBuilder();

            return $schema->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function validateSourceDatabase(string $sourceConnection): bool
    {
        try {
            if (! config("database.connections.{$sourceConnection}")) {
                $this->error("Source database connection '{$sourceConnection}' not configured.");
                $this->outputConfigurationHelp($sourceConnection);

                return false;
            }

            DB::connection($sourceConnection)->getPDO();
            $this->info('✅ Source database connection verified');

            return true;
        } catch (\Throwable $e) {
            $this->error('Cannot connect to source database: '.$e->getMessage());
            $this->outputConfigurationHelp($sourceConnection);

            return false;
        }
    }

    protected function runPreMigrationValidation(string $sourceConnection, string $startDate, string $endDate): bool
    {
        $this->line('');
        $this->info('Pre-migration validation:');

        // Check target database connection
        try {
            $targetConnection = config('database.default');
            if (! $targetConnection || ! config("database.connections.{$targetConnection}")) {
                $this->error('  ❌ Target database connection not configured');

                return false;
            }
            DB::connection($targetConnection)->getPDO();
            $this->info('  ✅ Target database connection verified');
        } catch (\Throwable $e) {
            $this->error('  ❌ Cannot connect to target database: '.$e->getMessage());

            return false;
        }

        // Check target database schema
        try {
            foreach (array_merge(...array_column($this->phases, 'tables')) as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    $this->warn("  ⚠️  Target table '{$table}' does not exist (will be skipped)");
                }
            }
            $this->info('  ✅ Target tables checked');
        } catch (\Throwable $e) {
            $this->warn('  ⚠️  Could not verify all target tables: '.$e->getMessage());
        }

        // Check source database has tables
        try {
            $sourceTableCount = 0;
            foreach (array_merge(...array_column($this->phases, 'tables')) as $table) {
                if (DB::connection($sourceConnection)->getSchemaBuilder()->hasTable($table)) {
                    $sourceTableCount++;
                }
            }

            if ($sourceTableCount === 0) {
                $this->error('  ❌ No source tables found in source database');

                return false;
            }

            $this->info("  ✅ Found {$sourceTableCount} source tables");
        } catch (\Throwable $e) {
            $this->error('  ❌ Could not verify source tables: '.$e->getMessage());

            return false;
        }

        $this->info("  ✅ Found {$sourceTableCount} source tables");

        return true;
    }

    protected function runPostMigrationValidation(string $sourceConnection, string $startDate, string $endDate): void
    {
        $this->line('');
        $this->info('Post-migration validation:');

        $dateRange = Carbon::createFromFormat('Y-m-d', $startDate)->format('M d, Y').' → '.
                     Carbon::createFromFormat('Y-m-d', $endDate)->format('M d, Y');

        $this->line("  Date Range: {$dateRange}");
        $this->line('');

        $criticalTables = ['users', 'products', 'orders'];

        foreach ($criticalTables as $table) {
            if (! DB::table($table)->exists()) {
                continue;
            }

            $sourceCount = DB::connection($this->option('source'))
                ->table($table)
                ->whereBetween('created_at', [
                    Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay(),
                ])
                ->whereNull('deleted_at')
                ->count();

            $targetCount = DB::table($table)
                ->whereBetween('created_at', [
                    Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay(),
                ])
                ->count();

            $status = $sourceCount === $targetCount ? '✅' : '⚠️ ';
            $this->line("  {$status} {$table}: source={$sourceCount}, target={$targetCount}");
        }
    }

    protected function disableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('SET session_replication_role = replica;');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
    }

    protected function enableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('SET session_replication_role = default;');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    protected function outputHeading(string $text): void
    {
        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════╗');
        $this->info("║  {$text}");
        $this->info('╚═══════════════════════════════════════════════════════╝');
        $this->line('');
    }

    protected function outputPhase(int $number, array $config, string $mode = ''): void
    {
        $tableCount = count($config['tables']);
        $modeText = $mode ? " ({$mode})" : '';
        $this->info("PHASE {$number}: {$config['name']}{$modeText}");
        if ($this->option('verbose')) {
            $this->line('  Tables: '.implode(', ', $config['tables']));
        }
    }

    protected function outputInfo(string $text): void
    {
        $this->line("  {$text}");
    }

    protected function outputSummary(): void
    {
        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════╗');
        $this->info('║  Migration Summary                                    ║');
        $this->info('╚═══════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("  Total Records Found:    {$this->stats['total']}");
        $this->line("  Successfully Migrated:  {$this->stats['migrated']}");
        $this->line("  Skipped/Duplicate:      {$this->stats['skipped']}");
        $this->line("  Failed:                 {$this->stats['failed']}");
        $this->line('');

        if ($this->stats['failed'] === 0) {
            $this->info('✅ Migration completed successfully!');
        } else {
            $this->warn('⚠️ Migration completed with '.$this->stats['failed'].' failures.');
        }

        $this->line('');
    }

    protected function outputConfigurationHelp(string $connection): void
    {
        $this->line('');
        $this->warn('Configuration Help:');
        $this->line('');
        $this->line('Add the source database connection to config/database.php:');
        $this->line('');
        $this->line("  'connections' => [");
        $this->line("      '{$connection}' => [");
        $this->line("          'driver' => 'pgsql',");
        $this->line("          'host' => env('LEGACY_DB_HOST'),");
        $this->line("          'port' => env('LEGACY_DB_PORT', 5432),");
        $this->line("          'database' => env('LEGACY_DB_DATABASE'),");
        $this->line("          'username' => env('LEGACY_DB_USERNAME'),");
        $this->line("          'password' => env('LEGACY_DB_PASSWORD'),");
        $this->line('      ],');
        $this->line('  ]');
        $this->line('');
        $this->line('Then set environment variables in .env:');
        $this->line('');
        $this->line('  LEGACY_DB_HOST=your-legacy-db-host');
        $this->line('  LEGACY_DB_DATABASE=legacy_database');
        $this->line('  LEGACY_DB_USERNAME=username');
        $this->line('  LEGACY_DB_PASSWORD=password');
        $this->line('');
    }

    protected function isValidDate(string $date): bool
    {
        return (bool) \DateTime::createFromFormat('Y-m-d', $date);
    }
}
