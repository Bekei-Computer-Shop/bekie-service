<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ];
    }
}
