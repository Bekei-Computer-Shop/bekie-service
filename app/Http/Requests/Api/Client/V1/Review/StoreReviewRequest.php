<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product ID is required.',
            'product_id.uuid' => 'Product ID must be a valid UUID.',
            'product_id.exists' => 'The selected product does not exist.',
            'order_id.integer' => 'Order ID must be an integer.',
            'order_id.exists' => 'The selected order does not exist.',
            'rating.required' => 'Rating is required.',
            'rating.integer' => 'Rating must be an integer.',
            'rating.between' => 'Rating must be between 1 and 5.',
            'title.string' => 'Title must be a string.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'comment.string' => 'Comment must be a string.',
            'comment.max' => 'Comment cannot exceed 2000 characters.',
        ];
    }
}
