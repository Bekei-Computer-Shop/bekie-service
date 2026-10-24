<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\ResetAdministratorPasswordRequest;
use App\Http\Requests\Api\Admin\V1\StoreAdministratorRequest;
use App\Http\Requests\Api\Admin\V1\UpdateAdministratorRequest;
use App\Http\Resources\Api\Admin\V1\UserResource;
use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdministratorController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $administrators = User::query()
            ->where('is_admin', true)
            ->with('roles')
            ->latest()
            ->paginate($perPage);

        return $this->success([
            'items' => UserResource::collection($administrators->items())->resolve(),
            'pagination' => [
                'total' => $administrators->total(),
                'per_page' => $administrators->perPage(),
                'current_page' => $administrators->currentPage(),
                'last_page' => $administrators->lastPage(),
            ],
        ]);
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
            'recovery_email' => $data['recovery_email'] ?? null,
            'password' => Hash::make($data['password']),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::findById($data['role_id']);
        $administrator->assignRole($role);

        return $this->created(new UserResource($administrator));
    }

    public function update(UpdateAdministratorRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $password = $data['password'] ?? null;

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'recovery_email' => array_key_exists('recovery_email', $data) ? $data['recovery_email'] : $user->recovery_email,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'password' => $password ? Hash::make($password) : $user->password,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        $role = Role::findById($data['role_id']);
        $user->syncRoles([$role]);

        if ($password) {
            ApiToken::query()
                ->where('user_id', $user->id)
                ->where('scope', 'admin')
                ->update(['revoked' => true]);
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
