<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /** The badges the admin Customers screen can assign. */
    public const STATUSES = ['active', 'vip', 'inactive'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // `users.phone` is uniquely indexed, so a blank one has to arrive as
            // null rather than '' — two empty strings would collide.
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::in(self::STATUSES)],
            // The admin form has no password field: when it is omitted the
            // controller generates one, and the customer resets it themselves.
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
