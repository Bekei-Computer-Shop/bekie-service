<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')) ?: $this->getRoleNames(),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')) ?: $this->getAllPermissions()->pluck('name'),
        ];
    }
}
