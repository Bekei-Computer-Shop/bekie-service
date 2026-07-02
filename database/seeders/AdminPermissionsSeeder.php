<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent seeder that installs the canonical admin RBAC permission set,
 * three default roles (admin / manager / staff), and the role-permission
 * grants for each. Re-running is safe: every create-or-find uses
 * firstOrCreate and syncPermissions diff-checks.
 *
 * Permissions follow `<resource>.<action>` naming so the API and middleware
 * map cleanly onto Spatie's string keys.
 */
class AdminPermissionsSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        // Users
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.assign-role',

        // Roles
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign-permission',

        // Permissions
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',

        // Categories
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',

        // Brands
        'brands.view',
        'brands.create',
        'brands.update',
        'brands.delete',

        // Products
        'products.view',
        'products.create',
        'products.update',
        'products.delete',

        // Promotions
        'promotions.view',
        'promotions.create',
        'promotions.update',
        'promotions.delete',

        // Content
        'content.view',
        'content.create',
        'content.update',
        'content.delete',

        // Customers
        'customers.view',

        // Orders
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.delete',

        // Administrators
        'administrators.view',
        'administrators.create',
        'administrators.update',
        'administrators.delete',

        // Banners / slides
        'banners.view',
        'banners.create',
        'banners.update',
        'banners.delete',

        // Activity logs
        'logs.view',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_GRANTS = [
        'admin' => [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-role',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign-permission',
            'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
            'content.view', 'content.create', 'content.update', 'content.delete',
            'customers.view',
            'orders.view', 'orders.create', 'orders.update', 'orders.delete',
            'administrators.view', 'administrators.create', 'administrators.update', 'administrators.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'logs.view',
        ],
        'manager' => [
            'users.view', 'users.create', 'users.update',
            'roles.view',
            'permissions.view',
            'categories.view', 'categories.create', 'categories.update',
            'brands.view', 'brands.create', 'brands.update',
            'products.view', 'products.create', 'products.update',
            'promotions.view', 'promotions.create', 'promotions.update',
            'content.view', 'content.create', 'content.update',
            'customers.view',
            'orders.view', 'orders.create', 'orders.update',
            'banners.view', 'banners.create', 'banners.update',
            'logs.view',
        ],
        'staff' => [
            'users.view',
            'categories.view',
            'brands.view',
            'products.view',
            'promotions.view',
            'content.view',
            'customers.view',
            'orders.view',
            'banners.view',
            'logs.view',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            // 1. Ensure every canonical permission exists (guard_name=api).
            foreach (self::PERMISSIONS as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'api'],
                    ['name' => $name, 'guard_name' => 'api'],
                );
            }

            // 2. Ensure every default role exists.
            foreach (array_keys(self::ROLE_GRANTS) as $roleName) {
                Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'api'],
                    ['name' => $roleName, 'guard_name' => 'api'],
                );
            }

            // 3. Sync grants per role. syncPermissions diff-checks, so it's
            //    safe to re-run; revoked permissions are removed cleanly.
            foreach (self::ROLE_GRANTS as $roleName => $permissions) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'api')
                    ->first();

                if (! $role) {
                    continue;
                }

                $role->syncPermissions($permissions);
            }
        });
    }
}
