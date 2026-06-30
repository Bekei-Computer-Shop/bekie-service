<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Api\Admin\V1\User\AssignUserRolesRequest;
use App\Http\Requests\Api\Admin\V1\User\IndexUsersRequest;
use App\Http\Requests\Api\Admin\V1\User\StoreUserRequest;
use App\Http\Requests\Api\Admin\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\Admin\V1\UserResource;
use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin user-management endpoints. All routes are gated by
 * `AuthenticateAdminApiToken` (parent group) + `permission:` middleware
 * (per action). This controller adds belt+braces checks for the
 * self-protection rules described in the user-management plan:
 *
 *   1. Self cannot delete / ban / demote self.
 *   2. The last active super-admin cannot be removed, demoted, or banned.
 *   3. Destroying a user revokes all their `scope=admin` API tokens so a
 *      compromised token cannot outlive the user row.
 */
class UserController extends BaseAdminController
{
    public function index(IndexUsersRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc');
        $withTrashed = (bool) $request->input('with_trashed', false);
        $onlyTrashed = (bool) $request->input('only_trashed', false);

        $query = User::query()->with('roles');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif (! $withTrashed) {
            // default: hide soft-deleted
        } else {
            $query->withTrashed();
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like);
            });
        }

        if ($roleName = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleName)->where('guard_name', 'api'));
        }

        foreach (['is_admin', 'is_active', 'is_banned'] as $field) {
            if ($request->has($field)) {
                $query->where($field, filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN));
            }
        }

        $total = (clone $query)->count();
        $items = $query->orderBy($sort, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success([
            'items' => UserResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles.permissions']);

        return $this->success(new UserResource($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles'], $data['password_confirmation']);

        $user = DB::transaction(function () use ($data, $roles): User {
            $user = new User;
            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_admin' => (bool) ($data['is_admin'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_banned' => (bool) ($data['is_banned'] ?? false),
            ]);
            $user->save();

            if ($roles !== []) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        $user->load('roles');

        return $this->created(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user() ?? $request->attributes->get('authenticated_user');
        $isSelf = $actor instanceof User && $actor->id === $user->id;

        // --- Self-protection: lock out self-destructive actions ---
        if ($isSelf) {
            foreach (['is_admin', 'is_active', 'is_banned'] as $flag) {
                if (array_key_exists($flag, $data) && $data[$flag] === false) {
                    return $this->error("You cannot disable your own `{$flag}` flag.", 403);
                }
            }
        }

        // --- Last-super-admin guard ---
        $currentlySuper = $user->isSuperAdmin();
        $willStillBeSuper = $currentlySuper
            && (! array_key_exists('is_admin', $data) || $data['is_admin'] === true)
            && (! array_key_exists('is_active', $data) || $data['is_active'] === true)
            && (! array_key_exists('is_banned', $data) || $data['is_banned'] === false)
            && (! array_key_exists('roles', $data) || in_array(
                $this->adminRoleId(),
                (array) $data['roles'],
                true
            ) || in_array('admin', (array) $data['roles'], true));

        if ($currentlySuper && ! $willStillBeSuper && User::countActiveSuperAdminsExcept($user->id) === 0) {
            return $this->error('Cannot remove the last active super-admin.', 422);
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles'], $data['password_confirmation']);

        DB::transaction(function () use ($user, $data, $roles): void {
            // Whitelist fields we accept to avoid mass-assignment of unintended columns.
            $allowed = ['first_name', 'last_name', 'username', 'email', 'phone', 'password', 'is_admin', 'is_active', 'is_banned'];
            $update = array_intersect_key($data, array_flip($allowed));

            // Cast booleans to actual bools (validation already gave us bool|null).
            foreach (['is_admin', 'is_active', 'is_banned'] as $flag) {
                if (array_key_exists($flag, $update) && $update[$flag] !== null) {
                    $update[$flag] = (bool) $update[$flag];
                }
            }

            // Drop empty-password so the hash isn't replaced with an empty value.
            if (array_key_exists('password', $update) && ($update['password'] === null || $update['password'] === '')) {
                unset($update['password']);
            }

            if ($update !== []) {
                $user->fill($update);
                $user->save();
            }

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
        });

        $user->refresh()->load('roles');

        return $this->success(new UserResource($user));
    }

    public function destroy(User $user): JsonResponse
    {
        $actor = request()->user() ?? request()->attributes->get('authenticated_user');

        if ($actor instanceof User && $actor->id === $user->id) {
            return $this->error('You cannot delete your own account.', 403);
        }

        if ($user->isSuperAdmin() && User::countActiveSuperAdminsExcept($user->id) === 0) {
            return $this->error('Cannot delete the last active super-admin.', 422);
        }

        try {
            DB::transaction(function () use ($user): void {
                // Revoke admin-scoped tokens BEFORE soft-delete so the row
                // doesn't get touched by the SoftDeletes deleted_at update.
                ApiToken::query()
                    ->where('user_id', $user->id)
                    ->where('scope', 'admin')
                    ->update(['revoked' => true]);

                $user->delete(); // soft delete
            });
        } catch (Throwable $e) {
            Log::error('Admin user delete failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return $this->error('Failed to delete user.', 500);
        }

        return $this->noContent();
    }

    public function restore(int $userId): JsonResponse
    {
        /** @var User|null $user */
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();
        $user->load('roles');

        return $this->success(new UserResource($user));
    }

    public function assignRoles(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if ($user->isSuperAdmin() && ! in_array($this->adminRoleId(), (array) ($data['roles'] ?? []), true)
            && ! in_array('admin', (array) ($data['roles'] ?? []), true)
            && User::countActiveSuperAdminsExcept($user->id) === 0
        ) {
            return $this->error('Cannot remove the `admin` role from the last active super-admin.', 422);
        }

        $user->syncRoles($data['roles']);
        $user->load('roles');

        return $this->success(new UserResource($user));
    }

    public function revokeRole(User $user, Role $role): JsonResponse
    {
        if ($role->name === 'admin' && $user->isSuperAdmin() && User::countActiveSuperAdminsExcept($user->id) === 0) {
            return $this->error('Cannot remove the `admin` role from the last active super-admin.', 422);
        }

        $user->removeRole($role);
        $user->load('roles');

        return $this->success(new UserResource($user));
    }

    /**
     * Look up the Spatie `admin` role id once per request, lazily cached.
     */
    private function adminRoleId(): ?int
    {
        static $id = null;

        if ($id === null) {
            $id = Role::query()->where('name', 'admin')->where('guard_name', 'api')->value('id');
            $id = $id !== null ? (int) $id : 0;
        }

        return $id ?: null;
    }
}
