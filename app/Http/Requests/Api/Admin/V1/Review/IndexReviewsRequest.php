<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Review;

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
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,hidden'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'q' => ['sometimes', 'string', 'max:255'],
            'product_id' => ['sometimes', 'uuid', 'exists:products,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: pending, approved, rejected, hidden.',
            'rating.between' => 'Rating must be between 1 and 5.',
            'product_id.uuid' => 'Product ID must be a valid UUID.',
            'product_id.exists' => 'The selected product does not exist.',
            'customer_id.integer' => 'Customer ID must be an integer.',
            'customer_id.exists' => 'The selected customer does not exist.',
        ];
    }
}
