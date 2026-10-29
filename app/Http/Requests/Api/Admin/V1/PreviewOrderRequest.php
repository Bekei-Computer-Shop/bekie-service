<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The part of an order that decides its promotions: who is buying and which
 * lines. Reuses StoreOrderRequest's line rules so a preview rejects exactly
 * what the real order would.
 */
class PreviewOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: the form can preview before a customer is picked. A
            // promotion with a per-customer limit then reads as unused.
            'customer_id' => ['sometimes', 'nullable', 'exists:users,id'],
            ...Arr::only((new StoreOrderRequest)->rules(), [
                'items',
                'items.*.product_id',
                'items.*.qty',
                'items.*.unit_price',
            ]),
        ];
    }
}
