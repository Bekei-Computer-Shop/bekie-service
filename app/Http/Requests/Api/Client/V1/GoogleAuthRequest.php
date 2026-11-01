<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The ID token ("credential") the website receives from Google Sign-In.
            'id_token' => ['required', 'string', 'max:4096'],
        ];
    }
}
