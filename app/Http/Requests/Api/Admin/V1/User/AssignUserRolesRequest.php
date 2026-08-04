<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUserRolesRequest extends FormRequest
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
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists((new Role)->getTable(), 'id')->where('guard_name', 'api')],
        ];
    }
}
