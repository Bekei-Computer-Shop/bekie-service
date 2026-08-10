<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The canonical order statuses. Kept in sync with the admin UI (the status
     * dropdown in OrderDetailView.vue and the filter tabs in OrdersView.vue).
     */
    public const STATUSES = ['pending', 'processing', 'completed', 'cancelled'];

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:'.implode(',', self::STATUSES)],
            // Payment state is recorded here rather than at creation — a new
            // order cannot already be paid, failed or refunded.
            'payment_status' => ['sometimes', 'in:'.implode(',', StoreOrderRequest::PAYMENT_STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
