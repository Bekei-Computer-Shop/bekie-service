<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required.',
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password.mixed_case' => 'New password must contain both uppercase and lowercase letters.',
            'new_password.numbers' => 'New password must contain at least one number.',
            'new_password.symbols' => 'New password must contain at least one special character.',
            'new_password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
