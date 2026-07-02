<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Http\Requests\Admin\UpdateAdministratorRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdministratorController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $administrators = User::role('admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'super-admin'))
            ->latest()
            ->paginate(15);

        return $this->success(UserResource::collection($administrators));
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(new UserResource($user));
    }

    public function store(StoreAdministratorRequest $request): JsonResponse
    {
        $data = $request->validated();

        $administrator = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $administrator->assignRole($data['role']);

        return $this->created(new UserResource($administrator));
    }

    public function update(UpdateAdministratorRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'] ? Hash::make($data['password']) : $user->password,
        ]);

        if (! $user->hasRole($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $this->success(new UserResource($user));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->noContent();
    }
}
