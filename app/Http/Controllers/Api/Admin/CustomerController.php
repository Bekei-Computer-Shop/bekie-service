<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $customerRole = Role::query()
            ->where('name', 'customer')
            ->where('guard_name', 'api')
            ->first();

        $customers = User::query()
            ->when($customerRole, fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('roles.id', $customerRole->id)))
            ->when(! $customerRole, fn ($query) => $query->where('is_admin', false))
            ->latest()
            ->paginate(15);

        return $this->success(UserResource::collection($customers));
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(new UserResource($user));
    }
}
