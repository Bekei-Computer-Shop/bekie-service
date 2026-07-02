<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category|null $target */
        $target = $this->route('category');
        $targetId = $target?->getKey();

        return [
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists((new Category)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Reject cycles: a category cannot be its own parent.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The selected parent category does not exist or is deleted.',
            'slug.regex' => 'Slug must be lowercase letters, digits, and dashes (e.g. `audio-gear`).',
        ];
    }

    /**
     * After validation, reject self-parent attempts in code (the validator
     * can't easily express `$value !== $this->route('category')->id`).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $target = $this->route('category');
            if (! $target instanceof Category) {
                return;
            }
            if ($this->filled('parent_id') && (int) $this->input('parent_id') === (int) $target->getKey()) {
                $v->errors()->add('parent_id', 'A category cannot be its own parent.');
            }
        });
    }
}
