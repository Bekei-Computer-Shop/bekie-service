<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Image file is required.',
            'image.image' => 'File must be a valid image.',
            'image.mimes' => 'Image must be a JPEG, PNG, GIF, or WebP file.',
            'image.max' => 'Image size cannot exceed 5MB.',
        ];
    }
}
