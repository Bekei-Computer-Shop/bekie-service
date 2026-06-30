<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Role;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'api')->whereNull('deleted_at'),
            ],
            'guard_name' => ['sometimes', 'string', 'in:api'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists((new Permission)->getTable(), 'id')->where('guard_name', 'api')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Role name must start with a lowercase letter and use only lowercase letters, digits, underscore, or dash.',
        ];
    }
}
