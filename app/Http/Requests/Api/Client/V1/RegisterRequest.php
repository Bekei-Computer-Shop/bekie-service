<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // At least one of email/phone is required.
            'email' => 'nullable|required_without:phone|email:rfc|max:255|unique:users,email',
            'phone' => 'nullable|required_without:email|string|max:20|regex:/^\+?[0-9 \-()]{7,20}$/|unique:users,phone',

            // Naming: 'name' is a convenience that the User mutator splits into first/last.
            'first_name' => 'required_without:name|nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:201',

            // Credentials.
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Either email or phone is required.',
            'phone.required_without' => 'Either email or phone is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
