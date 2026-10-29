<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateProductSerialRequest extends AdminBaseRequest
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
            // Uniqueness is checked by ProductSerialService::renumber(), which
            // compares without regard to case like receiving does.
            'serial_number' => ['sometimes', 'required', 'string', 'max:191'],
            'product_variant_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')
                    ->where('product_id', $this->route('productSerial')?->product_id),
            ],
            'status' => ['sometimes', 'in:available,reserved,sold,returned,in_service,repaired,replaced,damaged,lost,cancelled'],
            'warehouse' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
