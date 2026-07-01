<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists((new Category)->getTable(), 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', Rule::exists((new Brand)->getTable(), 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->whereNull('deleted_at')],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'track_inventory' => ['nullable', 'boolean'],
            'in_stock' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_digital' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            // Nested variants (replaces the existing pattern of separate endpoints).
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:191'],
            'variants.*.slug' => ['nullable', 'string', 'max:191'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'variants.*.min_stock_alert' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'variants.*.track_inventory' => ['nullable', 'boolean'],
            'variants.*.in_stock' => ['nullable', 'boolean'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.length' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.width' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.height' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.image' => ['nullable', 'string', 'max:255'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must be lowercase letters, digits, and dashes.',
            'sku.regex' => 'SKU may only contain letters, digits, dot, underscore, and dash.',
            'variants.*.sku.regex' => 'Variant SKU may only contain letters, digits, dot, underscore, and dash.',
        ];
    }

    /**
     * Cross-field validation: variant SKU must be unique per product. The
     * `Rule::unique` validator doesn't know the parent product id at
     * request time, so we defer uniqueness to the controller after the
     * product has been created and re-validate inside the transaction.
     *
     * Sale-price-greater-than-price check also runs in the controller.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            if ($this->filled('sale_price') && $this->filled('price')) {
                if ((float) $this->input('sale_price') > (float) $this->input('price')) {
                    $v->errors()->add('sale_price', 'Sale price must be less than or equal to price.');
                }
            }
        });
    }

    public function uniqueVariantSkus(): array
    {
        $skus = [];
        foreach ((array) $this->input('variants', []) as $i => $variant) {
            if (! empty($variant['sku'])) {
                $skus[] = ['index' => $i, 'sku' => (string) $variant['sku']];
            }
        }

        return $skus;
    }
}