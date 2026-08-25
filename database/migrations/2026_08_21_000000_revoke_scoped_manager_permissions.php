<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions the manager role held before it was scoped to the ten
     * capabilities the requirements name. AdminPermissionsSeeder only ever adds
     * grants — its docblock says it "deliberately retains assignments added
     * outside this seeder" — so editing ROLE_GRANTS cannot take these away on a
     * database that has already been seeded. Without this migration a manager
     * keeps real API access to Users, Stock and Logs while the portal hides them.
     *
     * @var list<string>
     */
    private const REVOKED = [
        'users.view', 'users.create', 'users.update',
        'roles.view',
        'permissions.view',
        'brands.view', 'brands.create', 'brands.update',
        'customers.view',
        'orders.create', 'orders.update',
        'logs.view',
        'media.delete',
        'stock.view', 'stock.update',
    ];

    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'manager')
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
        // Re-granting on rollback would hand the manager role back exactly the
        // access this migration exists to remove. To restore it deliberately,
        // add the permissions to ROLE_GRANTS['manager'] and re-run the seeder.
    }
};
