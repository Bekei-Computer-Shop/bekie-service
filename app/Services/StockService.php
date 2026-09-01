<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function adjust(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = [], ?string $sourceLocation = null, ?string $destinationLocation = null): StockMovement
    {
        return $this->recordMovement($stockable, 'adjust', $quantity, $reason, $description, $metadata, $sourceLocation, $destinationLocation);
    }

    public function reconcile(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = [], ?string $sourceLocation = null, ?string $destinationLocation = null): StockMovement
    {
        return $this->recordMovement($stockable, 'reconcile', $quantity, $reason, $description, $metadata, $sourceLocation, $destinationLocation);
    }

    public function stockIn(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = [], ?string $sourceLocation = null, ?string $destinationLocation = null): StockMovement
    {
        return $this->recordMovement($stockable, 'stock_in', $quantity, $reason, $description, $metadata, $sourceLocation, $destinationLocation);
    }

    public function stockOut(Model $stockable, int $quantity, ?string $reason = null, ?string $description = null, array $metadata = [], ?string $sourceLocation = null, ?string $destinationLocation = null): StockMovement
    {
        return $this->recordMovement($stockable, 'stock_out', $quantity, $reason, $description, $metadata, $sourceLocation, $destinationLocation);
    }

    public function transfer(Model $stockable, int $quantity, string $sourceLocation, string $destinationLocation, ?string $reason = null, array $metadata = []): StockMovement
    {
        return $this->recordMovement($stockable, 'transfer', $quantity, $reason, null, $metadata, $sourceLocation, $destinationLocation);
    }

    public function bulkAdjust(array $items, ?string $reason = null, ?string $reference = null): array
    {
        return DB::transaction(function () use ($items, $reason, $reference) {
            $movements = [];
            foreach ($items as $item) {
                $stockable = $item['stockable'] ?? null;
                if (! $stockable && isset($item['stockable_type']) && isset($item['stockable_id'])) {
                    $class = $item['stockable_type'];
                    $stockable = clone (new $class)->newInstance()->newQuery()->findOrFail($item['stockable_id']);
                }

                if (! $stockable) {
                    throw new \InvalidArgumentException('Stockable item not found or invalid.');
                }

                $itemReason = $item['reason'] ?? $reason;
                $itemReference = $item['reference'] ?? $reference;

                $sourceLocation = $item['metadata']['source_location'] ?? null;
                $destinationLocation = $item['metadata']['destination_location'] ?? null;

                $movements[] = $this->recordMovement(
                    $stockable,
                    $item['movement_type'],
                    $item['quantity'],
                    $itemReason,
                    $itemReference,
                    $item['metadata'] ?? [],
                    $sourceLocation,
                    $destinationLocation
                );
            }

            return $movements;
        });
    }

    /**
     * Atomically move units between two stockables (product↔product, product↔
     * variant, variant↔variant).
     *
     * Both rows are locked for update so two concurrent transfers can never
     * oversell the source. A transfer-out movement is recorded against the
     * source and a transfer-in against the destination, inside a single
     * transaction — any failure rolls the whole operation back.
     *
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function transferBetween(
        Model $source,
        Model $destination,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $sourceWarehouse = null,
        ?string $destinationWarehouse = null,
        array $metadata = [],
    ): array {
        $this->assertStockable($source);
        $this->assertStockable($destination);

        if ($source->is($destination)) {
            throw new \InvalidArgumentException('Source and destination stock must be different.');
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Transfer quantity must be at least 1.');
        }

        return DB::transaction(function () use (
            $source,
            $destination,
            $quantity,
            $reason,
            $reference,
            $sourceWarehouse,
            $destinationWarehouse,
            $metadata,
        ): array {

            // Lock the source first, then the destination. Both rows are
            // resolved through their own query builder so the lock survives the
            // transaction; the models passed in may be detached/cached.
            $sourceRow = $source->newQuery()->whereKey($source->getKey())->lockForUpdate()->first();
            if (! $sourceRow) {
                throw new \InvalidArgumentException('Source stock does not exist.');
            }

            $destinationRow = $destination->newQuery()->whereKey($destination->getKey())->lockForUpdate()->first();
            if (! $destinationRow) {
                throw new \InvalidArgumentException('Destination stock does not exist.');
            }

            if ((int) $sourceRow->stock_quantity < $quantity) {
                throw new \InvalidArgumentException(
                    "Insufficient stock on source (current on hand: {$sourceRow->stock_quantity})."
                );
            }

            $sourcePrevious = (int) $sourceRow->stock_quantity;
            $sourceNew = $sourcePrevious - $quantity;
            $destinationPrevious = (int) $destinationRow->stock_quantity;
            $destinationNew = $destinationPrevious + $quantity;

            $sourceRow->forceFill([
                'stock_quantity' => $sourceNew,
                'in_stock' => $sourceNew > 0,
            ])->save();

            $destinationRow->forceFill([
                'stock_quantity' => $destinationNew,
                'in_stock' => $destinationNew > 0,
            ])->save();

            $out = $sourceRow->stockMovements()->create([
                'movement_type' => 'transfer',
                'quantity' => $quantity,
                'previous_quantity' => $sourcePrevious,
                'new_quantity' => $sourceNew,
                'reason' => $reason,
                'reference' => $reference,
                'metadata' => array_merge($metadata, ['direction' => 'out']),
                'source_location' => $sourceWarehouse,
                'destination_location' => $destinationWarehouse,
                'created_by_id' => auth()->check() ? auth()->id() : null,
            ]);

            $in = $destinationRow->stockMovements()->create([
                'movement_type' => 'transfer',
                'quantity' => $quantity,
                'previous_quantity' => $destinationPrevious,
                'new_quantity' => $destinationNew,
                'reason' => $reason,
                'reference' => $reference,
                'metadata' => array_merge($metadata, ['direction' => 'in']),
                'source_location' => $sourceWarehouse,
                'destination_location' => $destinationWarehouse,
                'created_by_id' => auth()->check() ? auth()->id() : null,
            ]);

            return ['out' => $out, 'in' => $in];
        });
    }

    /**
     * Update the replenishment settings for a stockable. Only the columns the
     * stockable actually owns are touched, so a variant (which has no
     * reorder_point) ignores that key without erroring.
     */
    public function updateSettings(Model $stockable, array $settings): void
    {
        $this->assertStockable($stockable);

        $allowed = ['min_stock_alert', 'reorder_point', 'max_stock_level', 'track_inventory'];
        $fillable = $stockable->getFillable();

        $fill = collect($settings)
            ->only($allowed)
            ->only($fillable)
            ->filter(fn ($value) => $value !== null)
            ->all();

        if ($fill !== []) {
            $stockable->update($fill);
        }
    }

    protected function assertStockable(Model $stockable): void
    {
        if (! $stockable instanceof Product && ! $stockable instanceof ProductVariant) {
            throw new \InvalidArgumentException('Only products and product variants can be managed for stock.');
        }
    }

    protected function recordMovement(Model $stockable, string $movementType, int $quantity, ?string $reason, ?string $description, array $metadata = [], ?string $sourceLocation = null, ?string $destinationLocation = null): StockMovement
    {
        if ($stockable instanceof ProductVariant || $stockable instanceof Product) {
            return DB::transaction(function () use ($stockable, $movementType, $quantity, $reason, $description, $metadata, $sourceLocation, $destinationLocation): StockMovement {
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
                    'source_location' => $sourceLocation,
                    'destination_location' => $destinationLocation,
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
