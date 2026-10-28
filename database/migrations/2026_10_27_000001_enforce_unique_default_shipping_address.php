<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $customerIds = DB::table('addresses')
            ->whereNotNull('user_id')
            ->where('type', 'shipping')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id');

        foreach ($customerIds as $customerId) {
            $addresses = DB::table('addresses')
                ->where('user_id', $customerId)
                ->where('type', 'shipping')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderByDesc('is_default')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id');

            DB::table('addresses')->where('user_id', $customerId)->update(['is_default' => false]);

            if ($addresses->isNotEmpty()) {
                DB::table('addresses')->where('id', $addresses->first())->update(['is_default' => true]);
            }
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX addresses_one_active_shipping_default_per_user_unique ON addresses (user_id) WHERE type = 'shipping' AND is_active = true AND is_default = true AND deleted_at IS NULL AND user_id IS NOT NULL");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE addresses ADD COLUMN active_default_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN type = 'shipping' AND is_active = 1 AND is_default = 1 AND deleted_at IS NULL THEN user_id ELSE NULL END) STORED");
            DB::statement('CREATE UNIQUE INDEX addresses_one_active_shipping_default_per_user_unique ON addresses (active_default_user_id)');

            return;
        }

        throw new RuntimeException("The active-default address constraint is not implemented for {$driver}.");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('DROP INDEX addresses_one_active_shipping_default_per_user_unique ON addresses');
            DB::statement('ALTER TABLE addresses DROP COLUMN active_default_user_id');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS addresses_one_active_shipping_default_per_user_unique');
    }
};
