<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'target' => [
                'type' => $this->target_type,
                'id' => $this->target_id,
            ],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null,
            'timestamp' => $this->created_at?->toIso8601String(),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}
