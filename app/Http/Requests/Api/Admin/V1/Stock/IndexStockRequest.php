<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'warehouse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stock_status' => ['sometimes', 'nullable', Rule::in([
                'in_stock', 'low_stock', 'out_of_stock', 'overstock', 'pending',
                // Backward-compatible aliases.
                'low', 'out', 'healthy',
            ])],
            'updated_from' => ['sometimes', 'nullable', 'date'],
            'updated_to' => ['sometimes', 'nullable', 'date'],
            'include_variants' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in([
                'id', 'name', 'sku', 'stock_quantity', 'reserved_stock',
                'available_stock', 'updated_at', 'created_at', 'status',
            ])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
