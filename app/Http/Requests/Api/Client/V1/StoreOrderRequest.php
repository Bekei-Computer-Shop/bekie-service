<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cart_id' => 'required|exists:carts,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'address_id' => 'nullable|exists:addresses,id',
            'recipient_name' => 'required_without:address_id|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address_line_1' => 'required_without:address_id|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required_without:address_id|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'required_without:address_id|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
        ];
    }
}
