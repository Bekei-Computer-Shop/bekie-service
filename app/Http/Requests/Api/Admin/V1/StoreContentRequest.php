<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status' => ['required', 'in:draft,published,archived'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'audience' => ['sometimes', 'nullable', 'array'],
            'audience.type' => ['required_with:audience', 'in:all,users,roles,groups'],
            'audience.user_ids' => ['sometimes', 'array'],
            'audience.user_ids.*' => ['integer', 'exists:users,id'],
            'audience.roles' => ['sometimes', 'array'],
            'audience.roles.*' => ['string', 'max:100'],
            'audience.group_ids' => ['sometimes', 'array'],
            'audience.group_ids.*' => ['integer', 'exists:customer_groups,id'],
        ];
    }
}
