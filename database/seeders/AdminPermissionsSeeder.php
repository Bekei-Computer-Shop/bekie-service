<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent seeder that installs the canonical admin RBAC permission set,
 * four default roles (admin / manager / staff / user), and the baseline
 * role-permission grants for each. Re-running only adds missing grants; it
 * deliberately retains assignments added outside this seeder.
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
        'customers.create',
        'customers.update',
        'customers.delete',

        // Orders
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.delete',
        // Approval is deliberately separate from update: a manager approves
        // and rejects orders without being able to edit or create them.
        'orders.approve',

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

        // Media and stock
        'media.view',
        'media.create',
        'media.delete',
        'stock.view',
        'stock.update',

        // Authenticated admin self-service
        'admin.profile.view',
        'admin.profile.update',
        'admin.auth.logout',

        // Authenticated customer actions
        'client.auth.logout',
        'client.coupons.apply',
        'client.carts.manage',
        'client.wishlists.manage',
        'client.orders.manage',
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
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'orders.view', 'orders.create', 'orders.update', 'orders.delete',
            'administrators.view', 'administrators.create', 'administrators.update', 'administrators.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'logs.view',
            'media.view', 'media.create', 'media.delete',
            'stock.view', 'stock.update',
            'admin.profile.view', 'admin.profile.update', 'admin.auth.logout',
        ],
        // Scoped to the ten capabilities the requirements name: auth/profile
        // self-service, order approval, products, slides (banners), news and
        // website content, promotions, and product categories. No delete on
        // anything — that stays with admin. media.view/create are support for
        // uploading product, slide and news images; the portal never DELETEs
        // media, so media.delete is deliberately absent.
        'manager' => [
            'admin.profile.view', 'admin.profile.update', 'admin.auth.logout',
            'orders.view', 'orders.approve',
            'products.view', 'products.create', 'products.update',
            'categories.view', 'categories.create', 'categories.update',
            'banners.view', 'banners.create', 'banners.update',
            'content.view', 'content.create', 'content.update',
            'promotions.view', 'promotions.create', 'promotions.update',
            'media.view', 'media.create',
        ],
        // The same treatment as manager, one capability list shorter: staff
        // has no Approval and no Slide, so no orders.* and no banners.* grant.
        // Everything else matches, including the no-delete rule and the
        // media.view/create support for product, news and promotion images.
        'staff' => [
            'admin.profile.view', 'admin.profile.update', 'admin.auth.logout',
            'products.view', 'products.create', 'products.update',
            'categories.view', 'categories.create', 'categories.update',
            'content.view', 'content.create', 'content.update',
            'promotions.view', 'promotions.create', 'promotions.update',
            'media.view', 'media.create',
        ],
        'user' => [
            'client.auth.logout',
            'client.coupons.apply',
            'client.carts.manage',
            'client.wishlists.manage',
            'client.orders.manage',
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

            // 3. Add baseline grants without revoking existing assignments.
            foreach (self::ROLE_GRANTS as $roleName => $permissions) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'api')
                    ->first();

                if (! $role) {
                    continue;
                }

                foreach ($permissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            }
        });
    }
}
