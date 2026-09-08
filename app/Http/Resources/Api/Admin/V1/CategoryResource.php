<?php

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'icon' => $this->icon,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
