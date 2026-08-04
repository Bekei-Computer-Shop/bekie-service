<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Brand;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
        /** @var Brand|null $target */
        $target = $this->route('brand');
        $targetId = $target?->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('brands', 'slug')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'logo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'youtube' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must be lowercase letters, digits, and dashes (e.g. `sony-official`).',
        ];
    }
}