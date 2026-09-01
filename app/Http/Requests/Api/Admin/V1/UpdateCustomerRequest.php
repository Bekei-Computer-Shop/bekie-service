<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customerId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customerId)],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($customerId)],
            'address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::in(StoreCustomerRequest::STATUSES)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && blank($this->input('phone'))) {
            $this->merge(['phone' => null]);
        }
    }
}
