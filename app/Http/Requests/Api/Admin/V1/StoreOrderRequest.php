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
    public const PAYMENT_METHODS = ['aba_payway', 'cod'];

    /** Payment states the orders table documents. */
    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'in:USD,KHR'],

            // Order-level discount. Tax is calculated from store tax settings
            // on the server.
            'discount_total' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Shipping is priced on the server from the chosen method and the
            // lines' weight (OrderShippingCalculator); whether the method is
            // active and can carry the weight is checked there too. A typed
            // `shipping_total` is the older contract, kept for callers that
            // send no method, and cannot be combined with one.
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'shipping_total' => ['sometimes', 'nullable', 'numeric', 'min:0', 'prohibits:shipping_method_id'],

            'payment_method' => ['sometimes', 'nullable', 'in:'.implode(',', self::PAYMENT_METHODS)],
            'payment_status' => ['sometimes', 'nullable', 'in:'.implode(',', self::PAYMENT_STATUSES)],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'serial_numbers' => ['sometimes', 'array'],
            'serial_numbers.*' => ['string', 'max:191'],
        ];
    }
}
