<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Concerns;

use App\Models\Coupon;
use Illuminate\Validation\Rule;

/**
 * A promotion targets exactly one of products, categories or customer groups.
 * Ids for any other target type are rejected rather than silently dropped, so
 * a client that sends mixed targeting learns about it.
 */
trait ValidatesPromotionTargets
{
    /**
     * @param  Coupon|null  $promotion  the promotion being updated; null on create
     * @return array<string, list<mixed>>
     */
    protected function targetRules(?Coupon $promotion = null): array
    {
        $fields = ['target_type', ...array_merge(...array_map('array_keys', array_values(Coupon::TARGET_FIELDS)))];
        // On update, a payload that leaves targeting out leaves it alone.
        $touched = $promotion === null || $this->hasAny($fields);
        $targetType = $this->input('target_type', $promotion?->target_type);
        $targetType = in_array($targetType, Coupon::TARGET_TYPES, true) ? $targetType : null;

        $rules = [
            'target_type' => [
                Rule::requiredIf($touched && ($promotion === null || $promotion->target_type === null || $this->has('target_type'))),
                'string',
                Rule::in(Coupon::TARGET_TYPES),
            ],
            'product_ids.*' => ['string', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'variant_ids.*' => ['integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'customer_group_ids.*' => ['integer', Rule::exists('customer_groups', 'id')->where('is_active', true)],
        ];

        // A product promotion may target only specific variants, so it needs a
        // product or a variant; the other types need their primary field.
        $hasVariants = $targetType === Coupon::TARGET_PRODUCTS && filled($this->input('variant_ids'));

        foreach (Coupon::TARGET_FIELDS as $type => $typeFields) {
            foreach (array_keys($typeFields) as $field) {
                $isPrimary = Coupon::PRIMARY_TARGET_FIELDS[$type] === $field;
                $selected = $targetType === $type;
                $mustFill = $touched && $selected && $isPrimary && ! $hasVariants;

                $rules[$field] = array_values(array_filter([
                    Rule::requiredIf($mustFill),
                    // A missing or empty array passes `prohibited`, so a client
                    // may still send `[]` for the target types it is not using.
                    Rule::prohibitedIf($targetType !== null && ! $selected),
                    'array',
                    $mustFill ? 'min:1' : null,
                ]));
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function targetMessages(): array
    {
        return [
            'target_type.required' => 'Choose what this promotion targets: products, categories, or customer groups.',
            'target_type.in' => 'Choose what this promotion targets: products, categories, or customer groups.',
            'product_ids.required' => 'Select at least one product or variant.',
            'product_ids.min' => 'Select at least one product or variant.',
            'category_ids.required' => 'Select at least one category.',
            'category_ids.min' => 'Select at least one category.',
            'customer_group_ids.required' => 'Select at least one customer group.',
            'customer_group_ids.min' => 'Select at least one customer group.',
            'product_ids.prohibited' => 'Products can only be selected for a product promotion.',
            'variant_ids.prohibited' => 'Variants can only be selected for a product promotion.',
            'category_ids.prohibited' => 'Categories can only be selected for a category promotion.',
            'customer_group_ids.prohibited' => 'Customer groups can only be selected for a customer group promotion.',
            'product_ids.*.exists' => 'A selected product does not exist or is inactive.',
            'variant_ids.*.exists' => 'A selected variant does not exist or is inactive.',
            'category_ids.*.exists' => 'A selected category does not exist or is inactive.',
            'customer_group_ids.*.exists' => 'A selected customer group does not exist or is inactive.',
        ];
    }
}
