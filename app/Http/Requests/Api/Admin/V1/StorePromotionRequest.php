<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            // The campaign label. Kept off `type` on purpose — see Coupon::KINDS.
            'kind' => ['sometimes', 'nullable', Rule::in(Coupon::KINDS)],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'user_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'banner_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
