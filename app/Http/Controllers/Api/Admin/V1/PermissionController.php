<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Api\Admin\V1\Permission\StorePermissionRequest;
use App\Http\Requests\Api\Admin\V1\Permission\UpdatePermissionRequest;
use App\Http\Resources\Api\Admin\V1\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 50);
        $page = (int) request()->input('page', 1);
        $withTrashed = (bool) request()->input('with_trashed', false);
        $onlyTrashed = (bool) request()->input('only_trashed', false);

        $query = Permission::query()->with('roles');

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
            'items' => PermissionResource::collection($items)->resolve(request()),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function show(Permission $permission): JsonResponse
    {
        $permission->load('roles');

        return $this->success(new PermissionResource($permission));
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'api',
        ]);

        return $this->created(new PermissionResource($permission));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $data = $request->validated();

        if ($data !== []) {
            $permission->fill($data);
            $permission->save();
        }

        return $this->success(new PermissionResource($permission));
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return $this->noContent();
    }
}
