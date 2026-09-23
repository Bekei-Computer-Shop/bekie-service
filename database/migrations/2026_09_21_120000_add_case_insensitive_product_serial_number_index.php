<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX product_serials_serial_number_lower_unique ON product_serials (LOWER(serial_number))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_serials_serial_number_lower_unique');
    }
};
