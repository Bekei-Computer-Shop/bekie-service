<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductImage $image */
        $image = $this->resource;

        return [
            'id' => $image->id,
            'product_id' => $image->product_id,
            // `image` is the stored value (normally an absolute Cloudinary URL);
            // `url` is the same value resolved against the disk when it happens
            // to be a relative path instead. Clients should render `url`.
            'image' => $image->image,
            'url' => $image->url,
            'disk' => $image->disk,
            'mime_type' => $image->mime_type,
            'file_size' => $image->file_size !== null ? (int) $image->file_size : null,
            'alt_text' => $image->alt_text,
            'title' => $image->title,
            'type' => $image->type,
            'is_primary' => (bool) $image->is_primary,
            'is_active' => (bool) $image->is_active,
            'sort_order' => (int) $image->sort_order,
            'created_at' => $image->created_at?->toIso8601String(),
            'updated_at' => $image->updated_at?->toIso8601String(),
        ];
    }
}
