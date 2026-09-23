<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['slide', 'news', 'page'])],
            'slug' => ['required_if:type,page', 'nullable', 'string', Rule::in([
                'about-us',
                'contact-us',
                'terms-and-conditions',
                'privacy-policy',
                'shipping-policy',
                'returns-policy',
                'warranty-policy',
                'faq',
                'support',
                'delivery-information',
                'size-guide',
                'careers',
            ]), Rule::unique('content_items', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status' => ['required', 'in:draft,published,archived'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
