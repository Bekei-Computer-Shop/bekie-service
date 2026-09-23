<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreProductSerialRequest extends AdminBaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'serial_numbers' => ['required', 'array', 'min:1'],
            'serial_numbers.*' => ['required', 'string', 'distinct', 'max:191'],
            'receiving_quantity' => ['sometimes', 'integer', 'min:1'],
            'warehouse' => ['sometimes', 'nullable', 'string', 'max:100'],
            'receiving_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
