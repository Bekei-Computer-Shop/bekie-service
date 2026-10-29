<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string for GET /products/{product}/reviews: the public list of a
 * product's approved reviews.
 */
class ListProductReviewsRequest extends FormRequest
{
    /** `sort` values, mapped onto columns by the controller. */
    public const SORTS = ['newest', 'oldest', 'highest', 'lowest', 'most_helpful'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORTS)],
            // Only reviews with this many stars, e.g. from a "5 ★ (12)" filter chip.
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'verified_only' => ['sometimes', 'boolean'],
        ];
    }
}
