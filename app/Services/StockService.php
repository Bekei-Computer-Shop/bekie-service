<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function adjust(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'adjust', $quantity, $reason, $description, $metadata);
    }

    public function reconcile(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'reconcile', $quantity, $reason, $description, $metadata);
    }

    public function stockIn(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'stock_in', $quantity, $reason, $description, $metadata);
    }

    public function stockOut(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'stock_out', $quantity, $reason, $description, $metadata);
    }

    public function transfer(Model $stockable, int $quantity, string $sourceLocation, string $destinationLocation, ?string $reason = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'transfer', $quantity, $reason, null, array_merge($metadata, [
            'source_location' => $sourceLocation,
            'destination_location' => $destinationLocation,
        ]));
    }

    protected function recordMovement(Model $stockable, string $movementType, int $quantity, ?string $reason, ?string $description, array $metadata = []): StockMovement
    {
        if ($stockable instanceof ProductVariant || $stockable instanceof Product) {
            return DB::transaction(function () use ($stockable, $movementType, $quantity, $reason, $description, $metadata): StockMovement {
                $previousQuantity = (int) $stockable->stock_quantity;
                $newQuantity = $this->calculateNewQuantity($stockable, $movementType, $quantity);

                if ($newQuantity < 0) {
                    throw new \InvalidArgumentException('Stock quantity cannot be negative.');
                }

                $stockable->forceFill([
                    'stock_quantity' => $newQuantity,
                    'in_stock' => $newQuantity > 0,
                ])->save();

                return $stockable->stockMovements()->create([
                    'movement_type' => $movementType,
                    'quantity' => $quantity,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newQuantity,
                    'reason' => $reason,
                    'reference' => $description,
                    'metadata' => $metadata,
                    'source_location' => $metadata['source_location'] ?? null,
                    'destination_location' => $metadata['destination_location'] ?? null,
                    'created_by_id' => auth()->check() ? auth()->id() : null,
                ]);
            });
        }

        throw new \InvalidArgumentException('Only products and product variants can be managed for stock.');
    }

    protected function calculateNewQuantity(Model $stockable, string $movementType, int $quantity): int
    {
        $currentQuantity = (int) $stockable->stock_quantity;

        return match ($movementType) {
            'adjust' => $currentQuantity + $quantity,
            'reconcile' => $quantity,
            'stock_in' => $currentQuantity + $quantity,
            'stock_out' => $currentQuantity - $quantity,
            'transfer' => $currentQuantity - $quantity,
            default => $currentQuantity,
        };
    }
}
