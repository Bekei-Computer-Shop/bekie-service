<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Brand;

use Illuminate\Foundation\Http\FormRequest;

class IndexBrandsRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'with_trashed' => ['nullable', 'boolean'],
            'only_trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:id,name,sort_order,created_at,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
