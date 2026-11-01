<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Supplier|null $target */
        $target = $this->route('supplier');
        $targetId = $target?->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('suppliers', 'code')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Code may only contain letters, digits, dots, dashes and underscores (e.g. `SUP-001`).',
        ];
    }
}
