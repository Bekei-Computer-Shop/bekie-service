<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Api\Admin\V1\Role\StoreRoleRequest;
use App\Http\Requests\Api\Admin\V1\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Api\Admin\V1\Role\UpdateRoleRequest;
use App\Http\Resources\Api\Admin\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 25);
        $page = (int) request()->input('page', 1);
        $withTrashed = (bool) request()->input('with_trashed', false);
        $onlyTrashed = (bool) request()->input('only_trashed', false);

        $query = Role::query()->with('permissions');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($withTrashed) {
            $query->withTrashed();
        }

        if ($search = request()->input('q')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $this->success([
            'items' => RoleResource::collection($items)->resolve(request()),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = DB::transaction(function () use ($data, $permissions): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'api',
            ]);

            if ($permissions !== []) {
                $role->syncPermissions($permissions);
            }

            return $role;
        });

        $role->load('permissions');

        return $this->created(new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        DB::transaction(function () use ($role, $data, $permissions): void {
            if ($data !== []) {
                $role->fill($data);
                $role->save();
            }

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }
        });

        $role->refresh()->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        // Block deletion of seeded platform roles to keep the RBAC surface stable.
        if (in_array($role->name, ['admin', 'manager', 'staff'], true)) {
            return $this->error("The `{$role->name}` role is a platform role and cannot be deleted.", 422);
        }

        $role->delete();

        return $this->noContent();
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role->syncPermissions($data['permissions']);
        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }
}
