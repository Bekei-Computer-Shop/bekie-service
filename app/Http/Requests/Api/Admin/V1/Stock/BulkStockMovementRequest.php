<?php

namespace App\Http\Requests\Api\Admin\V1\Stock;

use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

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
                if (array_key_exists('stockable_id', $item)) {
                    $item['stockable_id'] = (string) $item['stockable_id'];
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
            'items.*.serial_numbers' => ['nullable', 'array'],
            'items.*.serial_numbers.*' => ['required', 'string', 'distinct', 'max:191'],
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
                    $absQty = abs($qty);

                    if ($item['movement_type'] === 'adjust' && $qty === 0) {
                        $validator->errors()->add("items.{$index}.quantity", 'Quantity cannot be zero for adjust.');
                    }
                    if ($item['movement_type'] === 'reconcile' && $qty < 0) {
                        $validator->errors()->add("items.{$index}.quantity", 'Quantity must be at least 0 for reconcile.');
                    }
                    if (in_array($item['movement_type'], ['stock_in', 'stock_out', 'transfer']) && $absQty < 1) {
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
                            $newQty -= $absQty;
                            break;
                        case 'reconcile':
                            $newQty = $qty;
                            break;
                    }

                    if ($newQty < 0) {
                        $validator->errors()->add("items.{$index}.quantity", "Resulting stock cannot be negative (current: {$current}).");
                    }

                    $serialNumbers = array_values(array_filter(array_map(static fn ($serial) => trim((string) $serial), $item['serial_numbers'] ?? []), static fn ($serial) => $serial !== ''));
                    $normalizedSerials = array_map('strtoupper', $serialNumbers);
                    if (count($normalizedSerials) !== count(array_unique($normalizedSerials))) {
                        $validator->errors()->add("items.{$index}.serial_numbers", 'Serial numbers must be unique within a row.');
                    }

                    $currentQty = (int) ($stockable->stock_quantity ?? 0);
                    $adjQty = (int) $qty;
                    $existingSerialCount = ProductSerial::query()
                        ->when($stockable instanceof ProductVariant, fn ($query) => $query->where('product_variant_id', (int) $stockable->id))
                        ->when(! $stockable instanceof ProductVariant, fn ($query) => $query->where('product_id', (string) $stockable->id))
                        ->where('status', ProductSerial::AVAILABLE)
                        ->count();

                    if ($item['movement_type'] === 'stock_out' || $item['movement_type'] === 'transfer' || $item['movement_type'] === 'adjust' && $adjQty < 0) {
                        $requiredSerialCount = (int) max(0, min(abs($adjQty), $existingSerialCount));
                    } else {
                        $requiredSerialCount = (int) max(0, ($currentQty + $adjQty) - $existingSerialCount);
                    }

                    if ($serialNumbers !== [] && count($serialNumbers) !== $requiredSerialCount) {
                        $validator->errors()->add("items.{$index}.serial_numbers", 'Serial number count must match the required quantity for this adjustment.');
                    }

                    $isSerialized = ($stockable instanceof ProductVariant || $stockable instanceof Product) && $stockable->is_serialized;
                    if ($isSerialized && count($serialNumbers) !== $requiredSerialCount) {
                        $validator->errors()->add("items.{$index}.serial_numbers", 'Each serialized variant unit requires the correct serial count based on the current stock and adjustment.');
                    }

                    if ($isSerialized && in_array($item['movement_type'], ['stock_out'], true) && $serialNumbers !== []) {
                        $existing = ProductSerial::query()
                            ->where('product_id', (string) $stockable->product_id ?? (string) $stockable->id)
                            ->when($stockable instanceof ProductVariant, fn ($query) => $query->where('product_variant_id', (int) $stockable->id))
                            ->whereIn(DB::raw('UPPER(serial_number)'), $normalizedSerials)
                            ->get()
                            ->keyBy(fn ($serial) => strtoupper((string) $serial->serial_number));

                        foreach ($normalizedSerials as $serialNumber) {
                            $record = $existing->get($serialNumber);
                            if (! $record) {
                                $validator->errors()->add("items.{$index}.serial_numbers", "Serial {$serialNumber} is not in stock for this variant.");
                                continue;
                            }

                            if ($record->status !== ProductSerial::AVAILABLE) {
                                $validator->errors()->add("items.{$index}.serial_numbers", "Serial {$serialNumber} is not available to remove from stock.");
                            }
                        }
                    }
                }
            }
        });
    }
}
