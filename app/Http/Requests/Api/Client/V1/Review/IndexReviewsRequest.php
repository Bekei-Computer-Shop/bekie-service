<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class IndexReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:created_at,rating,helpful_count'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
