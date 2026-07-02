<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Thin proxy for `$user->can('roles.*')` checks. Spatie's Gate auto-resolves
 * `roles.*` to `viewAnyRoles` / `createRole` / `updateRole` / etc. on the
 * AdminResourcePolicy class, but having a dedicated RolePolicy makes the
 * intent obvious in route definitions and keeps Laravel's model-based gate
 * lookup happy.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return (new AdminResourcePolicy)->viewAnyRoles($user);
    }

    public function view(User $user, Role $role): bool
    {
        return (new AdminResourcePolicy)->viewRole($user, $role);
    }

    public function create(User $user): bool
    {
        return (new AdminResourcePolicy)->createRole($user);
    }

    public function update(User $user, Role $role): bool
    {
        return (new AdminResourcePolicy)->updateRole($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return (new AdminResourcePolicy)->deleteRole($user, $role);
    }

    public function syncPermissions(User $user, Role $role): bool
    {
        return (new AdminResourcePolicy)->syncRolePermissions($user, $role);
    }
}
