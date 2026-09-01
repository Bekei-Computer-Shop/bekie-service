<?php

use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (AdminPermissionsSeeder::PERMISSIONS as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'api',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (array_keys(AdminPermissionsSeeder::ROLE_GRANTS) as $roleName) {
            DB::table('roles')->insertOrIgnore([
                'name' => $roleName,
                'guard_name' => 'api',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (AdminPermissionsSeeder::ROLE_GRANTS as $roleName => $permissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'api')->value('id');

            foreach ($permissions as $permission) {
                $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'api')->value('id');

                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $userRoleId = DB::table('roles')->where('name', 'user')->where('guard_name', 'api')->value('id');

        DB::table('users')->orderBy('id')->eachById(function (object $user) use ($userRoleId): void {
            $hasRole = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->exists();

            if (! $hasRole) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $userRoleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Authorization grants are deliberately retained to avoid revoking
        // access from existing users during a rollback.
    }
};
