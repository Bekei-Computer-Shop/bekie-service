<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Concerns\ValidatesPromotionTargets;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    use ValidatesPromotionTargets;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promotion = $this->route('promotion');
        $type = $this->input('type', $promotion->type);

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($promotion->id)],
            'type' => ['sometimes', 'in:percentage,fixed'],
            // The campaign label. Kept off `type` on purpose — see Coupon::KINDS.
            'kind' => ['sometimes', 'nullable', Rule::in(Coupon::KINDS)],
            // A type change must come with its value: the saved number means a
            // money amount for `fixed` and a share for `percentage`, so keeping
            // it would turn 15 off into 15% off. No `sometimes`: it would skip the
            // required check when value is left out.
            'value' => [
                Rule::requiredIf($this->has('type') && $type !== $promotion->type),
                'numeric',
                'min:0',
                Rule::when($type === 'percentage', ['max:100']),
            ],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'include_subcategories' => ['sometimes', 'boolean'],
            ...$this->targetRules($this->route('promotion')),
            'starts_at' => ['sometimes', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'user_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'banner_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->targetMessages(),
            'value.required' => 'Enter the value for the new promotion type.',
            'value.max' => 'A percentage promotion cannot exceed 100%.',
        ];
    }
}
