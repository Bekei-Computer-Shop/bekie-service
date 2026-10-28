<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1;

use App\Support\ShippingAddressRules;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ShippingAddressRules::rules(required: $this->isMethod('POST') || $this->isMethod('PUT'));
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = trim($value);
            }
        }

        if (isset($normalized['country'])) {
            $normalized['country'] = strtoupper($normalized['country']);
        }

        $this->merge($normalized);
    }
}
