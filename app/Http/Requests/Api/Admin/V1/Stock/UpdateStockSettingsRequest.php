<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = $this->input('stockable_type');
        if (is_string($type)) {
            $this->merge([
                'stockable_type' => match (strtolower(trim($type))) {
                    'product', 'app\\models\\product' => Product::class,
                    'variant', 'product_variant', 'productvariant', 'app\\models\\productvariant' => ProductVariant::class,
                    default => $type,
                },
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stockable_type' => ['required', Rule::in([Product::class, ProductVariant::class])],
            'stockable_id' => ['required', 'integer'],
            'min_stock_alert' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'max_stock_level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000000'],
            'reorder_point' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'track_inventory' => ['sometimes', 'boolean'],
        ];
    }
}
