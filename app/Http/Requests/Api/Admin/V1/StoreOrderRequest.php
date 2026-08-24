<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Payment methods the orders table documents. */
    public const PAYMENT_METHODS = ['cod', 'bank_transfer', 'stripe', 'paypal'];

    /** Payment states the orders table documents. */
    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],

            // Order-level amounts. Line items drive the subtotal; these adjust
            // it into the grand total.
            'discount_total' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax_total' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'shipping_total' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'payment_method' => ['sometimes', 'nullable', 'in:'.implode(',', self::PAYMENT_METHODS)],
            'payment_status' => ['sometimes', 'nullable', 'in:'.implode(',', self::PAYMENT_STATUSES)],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
