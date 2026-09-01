<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['source_type', 'destination_type'] as $field) {
            $type = $this->input($field);
            if (is_string($type)) {
                $this->merge([
                    $field => match (strtolower(trim($type))) {
                        'product', 'app\\models\\product' => Product::class,
                        'variant', 'product_variant', 'productvariant', 'app\\models\\productvariant' => ProductVariant::class,
                        default => $type,
                    },
                ]);
            }
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
        return [
            'source_type' => ['required', Rule::in([Product::class, ProductVariant::class])],
            'source_id' => ['required', 'integer'],
            'destination_type' => ['required', Rule::in([Product::class, ProductVariant::class])],
            'destination_id' => ['required', 'integer', 'different:source_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source_warehouse' => ['nullable', 'string', 'max:255'],
            'destination_warehouse' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $sourceType = $this->input('source_type');
            $sourceId = $this->input('source_id');
            $destinationType = $this->input('destination_type');
            $destinationId = $this->input('destination_id');

            if (! $sourceType || ! $sourceId || ! $destinationType || $destinationId === null) {
                return;
            }

            if ($sourceType === $destinationType && (int) $sourceId === (int) $destinationId) {
                $validator->errors()->add('destination_id', 'Source and destination must be different stock items.');

                return;
            }

            $source = $sourceType::query()->find($sourceId);
            if (! $source) {
                $validator->errors()->add('source_id', 'The selected source stock item does not exist.');

                return;
            }

            $destination = $destinationType::query()->find($destinationId);
            if (! $destination) {
                $validator->errors()->add('destination_id', 'The selected destination stock item does not exist.');

                return;
            }

            if (! $source->track_inventory) {
                $validator->errors()->add('source_id', 'The source is not inventory-tracked.');
            }

            $quantity = (int) $this->input('quantity');
            if ($quantity >= 1 && $quantity > (int) $source->stock_quantity) {
                $validator->errors()->add(
                    'quantity',
                    "Insufficient stock on source (current on hand: {$source->stock_quantity})."
                );
            }
        });
    }
}
