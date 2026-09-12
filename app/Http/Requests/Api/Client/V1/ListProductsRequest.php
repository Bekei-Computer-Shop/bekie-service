<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'array'],
            'category_id.*' => ['string', 'uuid'],
            'brand_id' => ['sometimes', 'array'],
            'brand_id.*' => ['string', 'uuid'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'sort_by' => ['sometimes', 'string', 'in:featured,price_asc,price_desc,newest,popular'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert single category_id or brand_id to array for consistent handling
        if ($this->has('category_id') && !is_array($this->category_id)) {
            $this->merge(['category_id' => [$this->category_id]]);
        }

        if ($this->has('brand_id') && !is_array($this->brand_id)) {
            $this->merge(['brand_id' => [$this->brand_id]]);
        }

        // Ensure min_price is not greater than max_price
        if ($this->filled('min_price') && $this->filled('max_price')) {
            if ((float) $this->min_price > (float) $this->max_price) {
                $this->merge(['max_price' => $this->min_price]);
            }
        }
    }
}
