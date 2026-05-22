<?php

namespace App\Http\Resources\Api\Client\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'stock_quantity' => $this->stock_quantity,
            'min_stock_alert' => $this->min_stock_alert,
            'track_inventory' => $this->track_inventory,
            'in_stock' => $this->in_stock,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnail' => $this->thumbnail,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_digital' => $this->is_digital,
            'views_count' => $this->views_count,
            'sales_count' => $this->sales_count,
            'sort_order' => $this->sort_order,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
