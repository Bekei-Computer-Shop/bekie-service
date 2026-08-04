<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Role;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
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
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', Rule::exists((new Permission)->getTable(), 'id')->where('guard_name', 'api')->whereNull('deleted_at')],
        ];
    }
}
