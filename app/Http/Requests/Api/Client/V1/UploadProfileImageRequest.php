<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Profile image is required.',
            'avatar.image' => 'The file must be a valid image.',
            'avatar.mimes' => 'The image must be in JPEG, PNG, JPG, or GIF format.',
            'avatar.max' => 'The image size cannot exceed 5 MB.',
        ];
    }
}
