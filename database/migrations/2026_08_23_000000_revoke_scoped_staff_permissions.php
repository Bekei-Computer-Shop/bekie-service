<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions the staff role held while it was a read-everything role,
     * before it was scoped to the capabilities the requirements name.
     * AdminPermissionsSeeder only ever adds grants — its docblock says it
     * "deliberately retains assignments added outside this seeder" — so editing
     * ROLE_GRANTS cannot take these away on a database that has already been
     * seeded. Without this migration a staff user keeps real API access to
     * Users, Orders, Slides, Stock and Logs while the portal hides them.
     *
     * @var list<string>
     */
    private const REVOKED = [
        'users.view',
        'brands.view',
        'customers.view',
        'orders.view',
        'banners.view',
        'logs.view',
        'stock.view',
    ];

    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'staff')
            ->where('guard_name', 'api')
            ->value('id');

        if ($roleId === null) {
            return;
        }

        // Named explicitly rather than syncing the role wholesale, so a grant
        // an operator added on purpose outside the seeder is not collateral.
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'api')
            ->whereIn('name', self::REVOKED)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        // Spatie caches the permission map; without this the old grants keep
        // answering hasPermissionTo() until the cache expires.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Re-granting on rollback would hand the staff role back exactly the
        // access this migration exists to remove. To restore it deliberately,
        // add the permissions to ROLE_GRANTS['staff'] and re-run the seeder.
    }
};
