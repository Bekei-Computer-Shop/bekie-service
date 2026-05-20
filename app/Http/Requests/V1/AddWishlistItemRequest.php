<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array',
        ];
    }
}
