<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing user representation. Distinct from the client-side
 * `UserResource` — exposes RBAC fields (roles, permissions, is_super_admin)
 * and never returns the password hash.
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'is_banned' => (bool) $user->is_banned,
            'is_super_admin' => $user->isSuperAdmin(),
            'role' => $user->role, // legacy read-only column mirrored from Spatie
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ])->all()
                : (method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : []),
            'permissions' => $user->relationLoaded('permissions') || $user->relationLoaded('roles')
                ? $user->getAllPermissions()->pluck('name')->all()
                : [],
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'api_tokens_count' => $this->whenCounted('apiTokens'),
            'admin_tokens_count' => $this->whenCounted('adminTokens'),
        ];
    }
}
