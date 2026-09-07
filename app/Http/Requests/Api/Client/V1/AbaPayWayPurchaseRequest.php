<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client\V1;

use Illuminate\Foundation\Http\FormRequest;

class AbaPayWayPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer'],
            'payment_option' => ['nullable', 'in:abapay_khqr,cards'],
            'platform' => ['sometimes', 'in:web,mobile'],
            'view_type' => ['sometimes', 'in:hosted_view,popup'],
            'return_url' => ['required', 'url', 'max:2048'],
            'cancel_url' => ['nullable', 'url', 'max:2048'],
            'continue_success_url' => ['nullable', 'url', 'max:2048'],
            'return_deeplink' => ['nullable', 'string', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $platform = $this->input('platform', 'web');

        $this->merge([
            'platform' => $platform,
            'view_type' => $this->input('view_type', $platform === 'mobile' ? 'hosted_view' : 'popup'),
        ]);
    }
}
