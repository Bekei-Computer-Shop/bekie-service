<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = $this->input('stockable_type');

        if (is_string($type)) {
            $normalized = match (strtolower(trim($type))) {
                'product', 'app\\models\\product' => Product::class,
                'variant', 'product_variant', 'productvariant', 'app\\models\\productvariant' => ProductVariant::class,
                default => $type,
            };

            $this->merge(['stockable_type' => $normalized]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $movementType = $this->input('movement_type');

        $quantityRules = match ($movementType) {
            'adjust' => ['required', 'integer', 'not_in:0'],
            'reconcile' => ['required', 'integer', 'min:0'],
            default => ['required', 'integer', 'min:1'],
        };

        return [
            'stockable_type' => ['required', Rule::in([Product::class, ProductVariant::class])],
            'stockable_id' => ['required', 'integer'],
            'movement_type' => ['required', Rule::in(['adjust', 'reconcile', 'stock_in', 'stock_out', 'transfer'])],
            'quantity' => $quantityRules,
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'source_location' => ['nullable', 'string', 'max:255'],
            'destination_location' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $stockableType = $this->input('stockable_type');
            $stockableId = $this->input('stockable_id');
            $movementType = $this->input('movement_type');
            $quantity = $this->input('quantity');

            if (! $stockableType || ! $stockableId) {
                return;
            }

            $modelClass = match ($stockableType) {
                Product::class => Product::class,
                ProductVariant::class => ProductVariant::class,
                default => null,
            };

            if ($modelClass === null) {
                return;
            }

            $stockable = $modelClass::query()->find($stockableId);

            if (! $stockable) {
                $validator->errors()->add('stockable_id', 'The selected stock item does not exist.');
                return;
            }

            if (is_numeric($quantity)) {
                $currentQuantity = (int) $stockable->stock_quantity;
                $qty = (int) $quantity;

                $newQuantity = match ($movementType) {
                    'adjust' => $currentQuantity + $qty,
                    'reconcile' => $qty,
                    'stock_in' => $currentQuantity + $qty,
                    'stock_out', 'transfer' => $currentQuantity - $qty,
                    default => $currentQuantity,
                };

                if ($newQuantity < 0) {
                    $validator->errors()->add('quantity', "Stock adjustment cannot result in negative stock (current on hand: {$currentQuantity}).");
                }
            }
        });
    }
}
