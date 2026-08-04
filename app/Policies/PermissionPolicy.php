<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return (new AdminResourcePolicy)->viewAnyPermissions($user);
    }

    public function view(User $user, Permission $permission): bool
    {
        return (new AdminResourcePolicy)->viewPermission($user, $permission);
    }

    public function create(User $user): bool
    {
        return (new AdminResourcePolicy)->createPermission($user);
    }

    public function update(User $user, Permission $permission): bool
    {
        return (new AdminResourcePolicy)->updatePermission($user, $permission);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return (new AdminResourcePolicy)->deletePermission($user, $permission);
    }
}
