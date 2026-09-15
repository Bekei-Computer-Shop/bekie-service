<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Review;

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
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,hidden'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: pending, approved, rejected, hidden.',
            'is_featured.boolean' => 'Is featured must be a boolean.',
        ];
    }
}
