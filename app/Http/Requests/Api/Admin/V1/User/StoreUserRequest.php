<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_banned' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
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

    /**
     * Normalise inputs (cast booleans, strip surrounding whitespace) so
     * controllers can rely on `$request->validated()` shape directly.
     *
     * @return array<string, mixed>
     */
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

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        return [
            'first_name' => 'Required given name.',
            'last_name' => 'Optional family name.',
            'username' => 'Required unique username (letters/digits/._-).',
            'email' => 'Required unique email address.',
            'phone' => 'Optional unique phone number.',
            'password' => 'Required password; must include mixed case, numbers, symbols and be at least 8 chars.',
            'password_confirmation' => 'Must match `password`.',
            'is_admin' => 'Grant platform super-admin flag (requires `users.update`).',
            'is_active' => 'Account active flag (default true).',
            'is_banned' => 'Account banned flag (default false).',
            'roles' => 'Array of Spatie role ids to assign at creation time.',
        ];
    }
}
