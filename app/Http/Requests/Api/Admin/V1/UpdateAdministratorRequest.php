<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'recovery_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
