<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
