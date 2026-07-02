<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing role representation. Returns the role's permission set
 * and a count of directly-attached users.
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->all()
                : [],
            'permission_names' => $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->all()
                : (method_exists($role, 'getPermissionNames') ? $role->getPermissionNames()->all() : []),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'deleted_at' => $role->deleted_at?->toIso8601String(),
        ];
    }
}
