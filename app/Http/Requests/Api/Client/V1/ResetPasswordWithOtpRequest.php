<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordWithOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ];
    }
}
