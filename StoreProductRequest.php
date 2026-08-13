<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'array'],
            'product.name' => ['required', 'string', 'max:255'],
            'product.slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'product.description' => ['nullable', 'string'],
            'product.short_description' => ['nullable', 'string', 'max:500'],
            'product.price' => ['required', 'numeric', 'min:0'],
            'product.sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'product.stock' => ['required', 'integer', 'min:0'],
            'product.category_id' => ['required', 'integer', 'exists:categories,id'],
            'product.brand_id' => ['required', 'integer', 'exists:brands,id'],
            'product.is_featured' => ['sometimes', 'boolean'],
            'product.is_active' => ['sometimes', 'boolean'],

            'variants' => ['sometimes', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100', 'unique:product_variants,sku'],
            'variants.*.price' => ['sometimes', 'numeric', 'min:0'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.attributes' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'product.slug.unique' => 'The slug has already been taken.',
            'product.sku.unique' => 'The SKU has already been taken.',
            'variants.*.sku.unique' => 'A variant with SKU :input already exists.',
        ];
    }
}
