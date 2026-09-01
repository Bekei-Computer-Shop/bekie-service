<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\ResetAdministratorPasswordRequest;
use App\Http\Requests\Api\Admin\V1\StoreAdministratorRequest;
use App\Http\Requests\Api\Admin\V1\UpdateAdministratorRequest;
use App\Http\Resources\Api\Admin\V1\UserResource;
use App\Models\ApiToken;
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

    public function resetPassword(ResetAdministratorPasswordRequest $request, User $user): JsonResponse
    {
        $actor = $request->user() ?? $request->attributes->get('authenticated_user');

        if (! $actor instanceof User || ! $actor->isSuperAdmin()) {
            return $this->error('Only a super-admin can reset an administrator password.', 403);
        }

        if (! $user->is_admin || ! $user->is_active || $user->is_banned) {
            return $this->error('The target administrator account is not active.', 422);
        }

        $user->update(['password' => Hash::make($request->validated('password'))]);

        ApiToken::query()
            ->where('user_id', $user->id)
            ->where('scope', 'admin')
            ->update(['revoked' => true]);

        return $this->success(message: 'Administrator password reset successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->noContent();
    }
}
