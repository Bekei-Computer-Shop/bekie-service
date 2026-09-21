<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductSerialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

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
            'warehouse' => ['sometimes', 'nullable', 'string', 'max:100'],
            'receiving_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
