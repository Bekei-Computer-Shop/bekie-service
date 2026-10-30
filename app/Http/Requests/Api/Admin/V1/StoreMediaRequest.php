<?php

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                Rule::when(
                    $this->input('resource_type', 'image') === 'video',
                    ['mimes:mp4,mov,webm,m4v,avi', 'max:102400'],
                    ['mimes:jpg,jpeg,png,gif,webp,svg', 'max:5120'],
                ),
            ],
            'resource_type' => ['sometimes', 'string', Rule::in(['image', 'video'])],
            'folder' => ['sometimes', 'string', 'alpha_dash', 'max:100'],
        ];
    }
}
