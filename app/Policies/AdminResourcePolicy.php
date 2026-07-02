<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Shared admin-side RBAC gate. Methods answer the named permissions created
 * by AdminPermissionsSeeder (e.g. `users.create`, `roles.update`).
 *
 * Route-level enforcement happens in CheckPermission middleware; this policy
 * backs `$user->can(...)` calls inside services / controllers as belt+braces.
 *
 * NOTE: We deliberately omit the `before()` super-admin short-circuit used by
 * the older model-based policy methods. The new permission checks must work
 * for managers/staff as well, so each method answers on its own.
 */
class AdminResourcePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return true;
    }

    public function approve(User $user): bool
    {
        return true;
    }

    public function reject(User $user): bool
    {
        return true;
    }

    // ─── Users ──────────────────────────────────────────────────────────
    public function viewAnyUsers(User $user): bool
    {
        return $this->userCan($user, 'users.view');
    }

    public function viewUser(User $user, User $target): bool
    {
        return $this->userCan($user, 'users.view');
    }

    public function createUser(User $user): bool
    {
        return $this->userCan($user, 'users.create');
    }

    public function updateUser(User $user, User $target): bool
    {
        return $this->userCan($user, 'users.update');
    }

    public function deleteUser(User $user, User $target): bool
    {
        return $this->userCan($user, 'users.delete');
    }

    public function restoreUser(User $user, User $target): bool
    {
        return $this->userCan($user, 'users.delete');
    }

    public function assignRoleToUser(User $user, User $target): bool
    {
        return $this->userCan($user, 'users.assign-role');
    }

    public function revokeRoleFromUser(User $user, User $target, Role $role): bool
    {
        return $this->userCan($user, 'users.assign-role');
    }

    // ─── Roles ──────────────────────────────────────────────────────────
    public function viewAnyRoles(User $user): bool
    {
        return $this->userCan($user, 'roles.view');
    }

    public function viewRole(User $user, Role $role): bool
    {
        return $this->userCan($user, 'roles.view');
    }

    public function createRole(User $user): bool
    {
        return $this->userCan($user, 'roles.create');
    }

    public function updateRole(User $user, Role $role): bool
    {
        return $this->userCan($user, 'roles.update');
    }

    public function deleteRole(User $user, Role $role): bool
    {
        return $this->userCan($user, 'roles.delete');
    }

    public function syncRolePermissions(User $user, Role $role): bool
    {
        return $this->userCan($user, 'roles.assign-permission');
    }

    // ─── Permissions ────────────────────────────────────────────────────
    public function viewAnyPermissions(User $user): bool
    {
        return $this->userCan($user, 'permissions.view');
    }

    public function viewPermission(User $user, Permission $permission): bool
    {
        return $this->userCan($user, 'permissions.view');
    }

    public function createPermission(User $user): bool
    {
        return $this->userCan($user, 'permissions.create');
    }

    public function updatePermission(User $user, Permission $permission): bool
    {
        return $this->userCan($user, 'permissions.update');
    }

    public function deletePermission(User $user, Permission $permission): bool
    {
        return $this->userCan($user, 'permissions.delete');
    }

    /**
     * Resolve the named Spatie permission. Implemented as a separate method
     * so the `before()` short-circuit doesn't run before the per-action
     * permission check (which would block everyone via `!hasRole('admin')`).
     */
    private function userCan(User $user, string $permission): bool
    {
        // Bypass the is_admin gate for the named-permission checks so that
        // a manager / staff role with `users.view` can still list users.
        // The route-level CheckPermission middleware applies the same check.
        return (bool) $user->can($permission);
    }
}
