<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255', 'nullable'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.auth()->id()],
            'phone' => ['sometimes', 'string', 'max:20', 'nullable', 'unique:users,phone,'.auth()->id()],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a string.',
            'first_name.max' => 'First name cannot exceed 255 characters.',
            'last_name.string' => 'Last name must be a string.',
            'last_name.max' => 'Last name cannot exceed 255 characters.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'This email is already taken.',
            'phone.string' => 'Phone must be a string.',
            'phone.max' => 'Phone cannot exceed 20 characters.',
            'phone.unique' => 'This phone number is already taken.',
        ];
    }
}
