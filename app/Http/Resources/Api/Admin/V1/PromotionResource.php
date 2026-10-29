<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Admin\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'kind' => $this->kind,
            'target_type' => $this->target_type,
            'value' => number_format((float) $this->value, 2, '.', ''),
            'min_order_amount' => $this->min_order_amount === null ? null : number_format((float) $this->min_order_amount, 2, '.', ''),
            'max_discount_amount' => $this->max_discount_amount === null ? null : number_format((float) $this->max_discount_amount, 2, '.', ''),
            'discount_value' => number_format((float) $this->discount_value, 2, '.', ''),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'user_limit' => $this->user_limit,
            'used_count' => $this->used_count,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            'banner_image' => $this->banner_image,
            'include_subcategories' => (bool) $this->include_subcategories,
            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values()),
            'variant_ids' => $this->whenLoaded('variants', fn () => $this->variants->pluck('id')->map(fn ($id) => (int) $id)->values()),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->map(fn ($id) => (int) $id)->values()),
            'customer_group_ids' => $this->whenLoaded('customerGroups', fn () => $this->customerGroups->pluck('id')->map(fn ($id) => (int) $id)->values()),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'short_description' => $product->short_description,
                'price' => $product->price !== null ? (float) $product->price : null,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'thumbnail' => $product->thumbnail,
                'category_id' => $product->category_id,
                'category' => $product->relationLoaded('category') && $product->category
                    ? ['id' => $product->category->id, 'name' => $product->category->name]
                    : null,
            ])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $variant->name,
                'sku' => $variant->sku,
            ])->values()),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'parent' => $category->relationLoaded('parent') && $category->parent
                    ? ['id' => $category->parent->id, 'name' => $category->parent->name]
                    : null,
            ])->values()),
            'customer_groups' => $this->whenLoaded('customerGroups', fn () => $this->customerGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
            ])->values()),
        ];
    }
}
