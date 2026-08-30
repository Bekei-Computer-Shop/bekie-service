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
        // The route admits orders.update and orders.approve. An approver moves
        // an order through its statuses and nothing more, so payment state and
        // notes are prohibited unless the caller also holds orders.update.
        // Resolved the way CheckPermission does it: the admin token guard
        // leaves the user on the request attributes, not always on user().
        $user = $this->user() ?? $this->attributes->get('authenticated_user');
        $statusOnly = ! $user || ! method_exists($user, 'can') || ! $user->can('orders.update');

        return [
            'status' => ['sometimes', 'in:'.implode(',', self::STATUSES)],
            // Payment state is recorded here rather than at creation — a new
            // order cannot already be paid, failed or refunded.
            'payment_status' => $statusOnly
                ? ['prohibited']
                : ['sometimes', 'in:'.implode(',', StoreOrderRequest::PAYMENT_STATUSES)],
            'notes' => $statusOnly
                ? ['prohibited']
                : ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_status.prohibited' => 'Updating payment status requires the orders.update permission.',
            'notes.prohibited' => 'Updating order notes requires the orders.update permission.',
        ];
    }
}
