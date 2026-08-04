<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $brandId = $this->route('brand')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('brands')->ignore($brandId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('brands')->ignore($brandId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
