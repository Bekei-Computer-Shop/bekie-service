<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            // Null for articles saved without one; link those by id.
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'image_url' => $this->image_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
