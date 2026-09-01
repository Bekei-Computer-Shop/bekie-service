<?php

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkStockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('items') && is_array($this->items)) {
            $items = $this->items;
            foreach ($items as &$item) {
                if (isset($item['stockable_type'])) {
                    $type = strtolower($item['stockable_type']);
                    if ($type === 'product') {
                        $item['stockable_type'] = Product::class;
                    } elseif ($type === 'variant' || $type === 'productvariant') {
                        $item['stockable_type'] = ProductVariant::class;
                    }
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.stockable_type' => ['required', 'string', 'in:'.Product::class.','.ProductVariant::class],
            'items.*.stockable_id' => ['required', 'string', 'max:36'],
            'items.*.movement_type' => ['required', 'string', 'in:adjust,reconcile,stock_in,stock_out,transfer'],
            'items.*.quantity' => ['required', 'integer'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.reference' => ['nullable', 'string', 'max:255'],
            'items.*.metadata' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (! isset($item['stockable_type']) || ! isset($item['stockable_id'])) {
                    continue;
                }

                $class = $item['stockable_type'];
                if (! class_exists($class)) {
                    continue;
                }

                $stockable = $class::find($item['stockable_id']);
                if (! $stockable) {
                    $validator->errors()->add("items.{$index}.stockable_id", 'The selected stockable item does not exist.');

                    continue;
                }

                if (isset($item['movement_type']) && isset($item['quantity'])) {
                    $qty = (int) $item['quantity'];
                    if ($item['movement_type'] === 'adjust' && $qty === 0) {
                        $validator->errors()->add("items.{$index}.quantity", 'Quantity cannot be zero for adjust.');
                    }
                    if ($item['movement_type'] === 'reconcile' && $qty < 0) {
                        $validator->errors()->add("items.{$index}.quantity", 'Quantity must be at least 0 for reconcile.');
                    }
                    if (in_array($item['movement_type'], ['stock_in', 'stock_out', 'transfer']) && $qty < 1) {
                        $validator->errors()->add("items.{$index}.quantity", 'Quantity must be at least 1 for this movement type.');
                    }

                    $current = (int) $stockable->stock_quantity;
                    $newQty = $current;
                    switch ($item['movement_type']) {
                        case 'adjust':
                        case 'stock_in':
                            $newQty += $qty;
                            break;
                        case 'stock_out':
                        case 'transfer':
                            $newQty -= $qty;
                            break;
                        case 'reconcile':
                            $newQty = $qty;
                            break;
                    }

                    if ($newQty < 0) {
                        $validator->errors()->add("items.{$index}.quantity", "Resulting stock cannot be negative (current: {$current}).");
                    }
                }
            }
        });
    }
}
