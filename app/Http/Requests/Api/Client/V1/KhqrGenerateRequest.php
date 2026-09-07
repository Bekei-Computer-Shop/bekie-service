<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class KhqrGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'platform' => ['sometimes', 'in:web,mobile'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform' => $this->input('platform', 'web'),
        ]);
    }
}
