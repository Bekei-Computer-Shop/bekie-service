<?php

namespace App\Http\Requests\Api\Client\V1;

use App\Support\ShippingAddressRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasAddressId = $this->filled('address_id');
        $hasInlineAddress = $this->hasAny([
            'recipient_name',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'delivery_note',
        ]);

        return [
            'cart_id' => 'required|exists:carts,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'address_id' => ['nullable', 'integer', Rule::prohibitedIf($hasInlineAddress)],
            'recipient_name' => ['required_without:address_id', Rule::prohibitedIf($hasAddressId), 'string', 'max:255'],
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address_line_1' => ['required_without:address_id', Rule::prohibitedIf($hasAddressId), 'string', 'max:255'],
            'address_line_2' => ['nullable', Rule::prohibitedIf($hasAddressId), 'string', 'max:255'],
            'city' => ['required_without:address_id', Rule::prohibitedIf($hasAddressId), 'string', 'max:100'],
            'state' => ['nullable', Rule::prohibitedIf($hasAddressId), 'string', 'max:100'],
            'postal_code' => ['required_without:address_id', Rule::prohibitedIf($hasAddressId), 'string', 'max:50'],
            'country' => ['required_without:address_id', Rule::prohibitedIf($hasAddressId), 'string', 'size:2', Rule::in(ShippingAddressRules::countryCodes())],
            'delivery_note' => ['nullable', Rule::prohibitedIf($hasAddressId), 'string', 'max:1000'],
            'save_address' => ['sometimes', 'boolean', Rule::prohibitedIf($hasAddressId)],
            'payment_method' => 'nullable|in:aba_payway,cod',
            'currency' => 'nullable|in:USD,KHR',
            'metadata' => 'nullable|array',
            'serial_numbers' => 'sometimes|array',
            'serial_numbers.*' => 'string|max:191',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Older clients send the country's name ("Cambodia"), not its code.
        if (is_string($this->input('country'))) {
            $this->merge(['country' => ShippingAddressRules::normalizeCountry($this->input('country'))]);
        }
    }
}
