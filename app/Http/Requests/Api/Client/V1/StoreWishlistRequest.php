<?php

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'session_id' => 'nullable|uuid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ];
    }
}
