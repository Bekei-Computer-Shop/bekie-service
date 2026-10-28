<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        $avatar = $this->resolveAvatar($this->avatar);

        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'avatar' => $avatar,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'is_banned' => (bool) $this->is_banned,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveAvatar(?string $avatar): ?string
    {
        if ($avatar === null || $avatar === '' || preg_match('#^(https?://|data:|//)#i', $avatar) === 1) {
            return $avatar;
        }

        return Storage::disk('public')->url($avatar);
    }
}
