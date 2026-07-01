<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product|null $target */
        $target = $this->route('product');
        $targetId = $target?->getKey();

        return [
            'category_id' => ['sometimes', 'integer', Rule::exists((new Category)->getTable(), 'id')->whereNull('deleted_at')],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists((new Brand)->getTable(), 'id')->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('products', 'sku')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($targetId)->whereNull('deleted_at')],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'min_stock_alert' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'track_inventory' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_digital' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],

            // Variant array. When `replace_variants` is omitted or true, the
            // submitted set replaces the existing set. When false, the
            // controller treats the array as additive.
            'replace_variants' => ['nullable', 'boolean'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $price = $this->input('price');
            $sale = $this->input('sale_price');
            if ($price !== null && $sale !== null && (float) $sale > (float) $price) {
                $v->errors()->add('sale_price', 'Sale price must be less than or equal to price.');
            }
        });
    }
}