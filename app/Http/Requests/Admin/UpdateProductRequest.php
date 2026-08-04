<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'nullable', 'exists:brands,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock_qty' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive'],
            'track_inventory' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_digital' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
