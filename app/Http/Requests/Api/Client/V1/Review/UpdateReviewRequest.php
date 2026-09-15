<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'string', 'max:255', 'nullable'],
            'comment' => ['sometimes', 'string', 'max:2000', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.integer' => 'Rating must be an integer.',
            'rating.between' => 'Rating must be between 1 and 5.',
            'title.string' => 'Title must be a string.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'comment.string' => 'Comment must be a string.',
            'comment.max' => 'Comment cannot exceed 2000 characters.',
        ];
    }
}
