<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Installs the suppliers.* permissions on databases seeded before they
     * existed and grants them to admin, the only role that manages stock.
     * AdminPermissionsSeeder carries the same grants for fresh installs.
     */
    private const PERMISSIONS = ['suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete'];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'api',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->value('id');
        if (! $adminRoleId) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'api')->value('id');

            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Authorization grants are deliberately retained to avoid revoking
        // access from existing users during a rollback.
    }
};
