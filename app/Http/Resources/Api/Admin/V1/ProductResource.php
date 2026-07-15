<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => $product->price !== null ? (float) $product->price : null,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
            'stock_quantity' => (int) $product->stock_quantity,
            'min_stock_alert' => (int) $product->min_stock_alert,
            'track_inventory' => (bool) $product->track_inventory,
            'in_stock' => (bool) $product->in_stock,
            'weight' => $product->weight !== null ? (float) $product->weight : null,
            'length' => $product->length !== null ? (float) $product->length : null,
            'width' => $product->width !== null ? (float) $product->width : null,
            'height' => $product->height !== null ? (float) $product->height : null,
            'thumbnail' => $product->thumbnail,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'is_active' => (bool) $product->is_active,
            'is_featured' => (bool) $product->is_featured,
            'is_digital' => (bool) $product->is_digital,
            'sort_order' => (int) $product->sort_order,
            'views_count' => (int) $product->views_count,
            'sales_count' => (int) $product->sales_count,
            'category' => $product->relationLoaded('category') && $product->category
                ? ['id' => $product->category->id, 'name' => $product->category->name, 'slug' => $product->category->slug]
                : null,
            'brand' => $product->relationLoaded('brand') && $product->brand
                ? ['id' => $product->brand->id, 'name' => $product->brand->name, 'slug' => $product->brand->slug]
                : null,
            'variants' => $product->relationLoaded('variants')
                ? ProductVariantResource::collection($product->variants)->resolve($request)
                : $this->whenCounted('variants'),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'deleted_at' => $product->deleted_at?->toIso8601String(),
        ];
    }
}