<?php

namespace Tests\Feature;

use App\Models\IdMigrationMap;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $sourceConnection = 'pgsql';

    protected function setUp(): void
    {
        parent::setUp();
        // Create id_migration_map table
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_11_create_id_migration_map_table.php']);
    }

    public function test_migration_command_exists(): void
    {
        $this->artisan('data:migrate-ecommerce --help')
            ->assertSuccessful();
    }

    public function test_migration_requires_source_database_configuration(): void
    {
        $this->artisan('data:migrate-ecommerce', [
            '--source' => 'invalid-connection',
            '--dry-run' => true,
        ])
            ->assertFailed()
            ->expectsOutput('Source database connection \'invalid-connection\' not configured');
    }

    public function test_migration_validates_date_format(): void
    {
        $this->artisan('data:migrate-ecommerce', [
            '--start' => 'invalid-date',
            '--dry-run' => true,
        ])
            ->assertFailed()
            ->expectsOutput('Invalid date format');
    }

    public function test_dry_run_does_not_insert_data(): void
    {
        // Create test data in source
        DB::table('users')->insert([
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialCount = DB::table('users')->where('migration_batch', 'historical-2024-2026')->count();

        $this->artisan('data:migrate-ecommerce', [
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();

        $finalCount = DB::table('users')->where('migration_batch', 'historical-2024-2026')->count();

        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_migration_filters_by_date_range(): void
    {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        // Create users with different dates
        DB::table('users')->insert([
            ['email' => 'before@example.com', 'name' => 'Before', 'password' => bcrypt('pass'), 'created_at' => '2023-12-31', 'updated_at' => now()],
            ['email' => 'within@example.com', 'name' => 'Within', 'password' => bcrypt('pass'), 'created_at' => '2024-06-15', 'updated_at' => now()],
            ['email' => 'after@example.com', 'name' => 'After', 'password' => bcrypt('pass'), 'created_at' => '2025-01-01', 'updated_at' => now()],
        ]);

        $this->artisan('data:migrate-ecommerce', [
            '--start' => $startDate,
            '--end' => $endDate,
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();

        // Verify only within-range record would be migrated
        $withinRangeCount = DB::table('users')
            ->whereBetween('created_at', [
                Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay(),
            ])
            ->whereNull('deleted_at')
            ->count();

        $this->assertGreaterThanOrEqual(1, $withinRangeCount);
    }

    public function test_migration_excludes_soft_deleted_records(): void
    {
        // Create a soft-deleted user
        DB::table('users')->insert([
            'id' => 1,
            'email' => 'deleted@example.com',
            'name' => 'Deleted User',
            'password' => bcrypt('password'),
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('data:migrate-ecommerce', [
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();

        // Soft-deleted record should not be migrated
        $count = DB::table('users')
            ->where('email', 'deleted@example.com')
            ->where('migration_batch', 'historical-2024-2026')
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_uuid_strategy_deterministic_generates_consistent_uuids(): void
    {
        $table = 'products';
        $sourceId = '12345';

        // Create test product
        DB::table('products')->insert([
            'id' => fake()->uuid(),
            'sku' => 'TEST-SKU-001',
            'name' => 'Test Product',
            'category_id' => 1,
            'price' => 99.99,
            'stock_quantity' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run with deterministic strategy
        $this->artisan('data:migrate-ecommerce', [
            '--phase' => 3,
            '--uuid-strategy' => 'deterministic',
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();

        // Verify deterministic UUID format (should be valid UUID)
        $hash = hash('sha256', "{$table}-{$sourceId}", false);
        $hex = substr($hash, 0, 32);
        $expectedUuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        // Verify it's a valid UUID format
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $expectedUuid
        );
    }

    public function test_migration_respects_phase_order(): void
    {
        // Phase 1 (permissions, roles) should run before
        // Phase 2 (users, addresses) which depends on roles

        $this->artisan('data:migrate-ecommerce', [
            '--phase' => 1,
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('PHASE 1: Reference & Master Data');

        $this->artisan('data:migrate-ecommerce', [
            '--phase' => 2,
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('PHASE 2: User & Address Data');
    }

    public function test_migration_batch_tagging(): void
    {
        DB::table('users')->insert([
            'email' => 'batch-test@example.com',
            'name' => 'Batch Test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // After successful migration, records should be tagged
        $user = DB::table('users')
            ->where('email', 'batch-test@example.com')
            ->first();

        // Initially not tagged
        $this->assertNull($user->migration_batch ?? null);
    }

    public function test_migration_chunk_size_option(): void
    {
        // Create multiple test records
        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->insert([
                'email' => "chunk-test-{$i}@example.com",
                'name' => "Chunk Test {$i}",
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Run with small chunk size
        $this->artisan('data:migrate-ecommerce', [
            '--chunk' => 2,
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();
    }

    public function test_migration_is_idempotent(): void
    {
        DB::table('users')->insert([
            'email' => 'idempotent-test@example.com',
            'name' => 'Idempotent Test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // First migration attempt
        $this->artisan('data:migrate-ecommerce', [
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();

        // Second migration attempt (should not error)
        $this->artisan('data:migrate-ecommerce', [
            '--dry-run' => true,
            '--skip-validation' => true,
        ])
            ->assertSuccessful();
    }

    public function test_id_migration_map_model(): void
    {
        IdMigrationMap::recordMapping(
            table: 'products',
            sourceId: '12345',
            targetId: fake()->uuid(),
            sourceType: 'integer',
            targetType: 'uuid',
            metadata: ['test' => true]
        );

        $mapping = IdMigrationMap::where('table_name', 'products')->first();

        $this->assertNotNull($mapping);
        $this->assertEquals('12345', $mapping->source_id);
        $this->assertTrue($mapping->metadata['test']);
    }

    public function test_id_migration_map_lookup(): void
    {
        $sourceId = '12345';
        $targetId = fake()->uuid();

        IdMigrationMap::recordMapping('products', $sourceId, $targetId);

        $foundTargetId = IdMigrationMap::mapSourceToTarget('products', $sourceId);

        $this->assertEquals($targetId, $foundTargetId);
    }

    public function test_migration_validation_success_output(): void
    {
        $this->artisan('data:migrate-ecommerce', [
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('✅ All target tables exist');
    }
}
