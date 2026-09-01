<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            // `image` is the stored value (normally an absolute Cloudinary URL);
            // `url` resolves relative paths against the disk. Clients should
            // render `url`.
            'image' => $this->image,
            'url' => $this->url,
            'alt_text' => $this->alt_text,
            'title' => $this->title,
            'type' => $this->type,
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}