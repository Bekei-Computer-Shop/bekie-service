<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `permission:users.view` middleware already gates this;
        // the FormRequest layer just needs to return true for validation to run.
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:64'],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_banned' => ['nullable', 'boolean'],
            'with_trashed' => ['nullable', 'boolean'],
            'only_trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:id,first_name,last_name,email,username,created_at,updated_at,last_login_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        return [
            'q' => 'Free-text search over email / first_name / last_name / username.',
            'role' => 'Filter by Spatie role name (e.g. `manager`).',
            'is_admin' => 'Filter by admin flag.',
            'is_active' => 'Filter by active flag.',
            'is_banned' => 'Filter by banned flag.',
            'with_trashed' => 'Include soft-deleted users.',
            'only_trashed' => 'Return only soft-deleted users.',
            'per_page' => 'Page size (1-200, default 25).',
            'page' => 'Page number (1-indexed).',
            'sort' => 'Column to sort by.',
            'direction' => 'Sort direction (asc|desc).',
        ];
    }
}
