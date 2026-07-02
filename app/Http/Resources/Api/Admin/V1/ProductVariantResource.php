<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'slug' => $variant->slug,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price !== null ? (float) $variant->price : null,
            'sale_price' => $variant->sale_price !== null ? (float) $variant->sale_price : null,
            'cost_price' => $variant->cost_price !== null ? (float) $variant->cost_price : null,
            'stock_quantity' => (int) $variant->stock_quantity,
            'min_stock_alert' => (int) $variant->min_stock_alert,
            'track_inventory' => (bool) $variant->track_inventory,
            'in_stock' => (bool) $variant->in_stock,
            'weight' => $variant->weight !== null ? (float) $variant->weight : null,
            'length' => $variant->length !== null ? (float) $variant->length : null,
            'width' => $variant->width !== null ? (float) $variant->width : null,
            'height' => $variant->height !== null ? (float) $variant->height : null,
            'image' => $variant->image,
            'attributes' => $variant->attributes ?? new \stdClass,
            'is_default' => (bool) $variant->is_default,
            'is_active' => (bool) $variant->is_active,
            'sort_order' => (int) $variant->sort_order,
            'created_at' => $variant->created_at?->toIso8601String(),
            'updated_at' => $variant->updated_at?->toIso8601String(),
            'deleted_at' => $variant->deleted_at?->toIso8601String(),
        ];
    }
}
