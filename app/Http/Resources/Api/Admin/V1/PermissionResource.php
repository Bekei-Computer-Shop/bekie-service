<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing permission representation. Includes the roles that grant
 * this permission so callers can audit who has access.
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Permission $permission */
        $permission = $this->resource;

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles' => $permission->relationLoaded('roles')
                ? $permission->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ])->all()
                : [],
            'roles_count' => $this->whenCounted('roles'),
            'created_at' => $permission->created_at?->toIso8601String(),
            'updated_at' => $permission->updated_at?->toIso8601String(),
            'deleted_at' => $permission->deleted_at?->toIso8601String(),
        ];
    }
}
