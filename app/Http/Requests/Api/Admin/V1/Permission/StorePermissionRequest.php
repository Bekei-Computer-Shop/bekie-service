<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
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
                'max:128',
                'regex:/^[a-z][a-z0-9_.-]*$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'api')->whereNull('deleted_at'),
            ],
            'guard_name' => ['sometimes', 'string', 'in:api'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must start with a lowercase letter and use only lowercase letters, digits, dot, underscore, or dash.',
        ];
    }
}
