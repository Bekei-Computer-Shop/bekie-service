<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $targetId = $target?->getKey();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'username' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($targetId)->whereNull('deleted_at')],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($targetId)->whereNull('deleted_at')],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($targetId)->whereNull('deleted_at')],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
            'is_admin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_banned' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', Rule::exists((new Role)->getTable(), 'id')->where('guard_name', 'api')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username may only contain letters, digits, dot, underscore, and dash.',
            'password.uncompromised' => 'This password has appeared in a known data breach. Please choose a different password.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['first_name', 'last_name', 'username', 'email', 'phone'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $payload[$field] = trim($this->input($field));
            }
        }

        foreach (['is_admin', 'is_active', 'is_banned'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($this->has('password') && $this->input('password') === '') {
            $payload['password'] = null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
