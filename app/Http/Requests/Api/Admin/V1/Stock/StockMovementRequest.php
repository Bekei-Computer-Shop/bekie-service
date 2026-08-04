<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
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
        return [
            'stockable_type' => ['required', Rule::in([Product::class, ProductVariant::class])],
            'stockable_id' => ['required', 'integer'],
            'movement_type' => ['required', Rule::in(['adjust', 'reconcile', 'stock_in', 'stock_out', 'transfer'])],
            'quantity' => ['required', 'integer', 'min:1'],
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

            if (! $modelClass::query()->whereKey($stockableId)->exists()) {
                $validator->errors()->add('stockable_id', 'The selected stock item does not exist.');
            }
        });
    }
}
