<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => $this->is_active ? 'active' : 'inactive',
            'category' => $this->whenLoaded('category', fn () => $this->category->name),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand->name),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
