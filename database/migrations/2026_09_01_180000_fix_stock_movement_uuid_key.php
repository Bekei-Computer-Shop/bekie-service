<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasColumn('stock_movements', 'stockable_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $column = DB::selectOne(
                "select data_type from information_schema.columns
                 where table_schema = current_schema()
                   and table_name = 'stock_movements'
                   and column_name = 'stockable_id'"
            );

            if ($column && $column->data_type !== 'character varying') {
                DB::statement('alter table stock_movements alter column stockable_id type varchar(36) using stockable_id::text');
            }

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('alter table stock_movements modify stockable_id varchar(36) null');
        }
    }

    public function down(): void
    {
        // Existing UUID references cannot safely be converted back to integers.
    }
};
