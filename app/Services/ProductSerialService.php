<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductSerialHistory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductSerialService
{
    /** @param array<int, string> $serialNumbers */
    public function receive(array $serialNumbers, string $productId, ?int $variantId = null, ?string $warehouse = null, ?string $reference = null, ?int $actorId = null, ?string $note = null): array
    {
        $serialNumbers = array_values(array_map(fn (string $serial): string => trim($serial), $serialNumbers));

        if (count($serialNumbers) === 0 || count($serialNumbers) !== count(array_unique($serialNumbers))) {
            throw new InvalidArgumentException('At least one unique serial number is required.');
        }

        $product = Product::query()->findOrFail($productId);
        $variant = $variantId ? ProductVariant::query()->findOrFail($variantId) : null;
        if ($variant && (string) $variant->product_id !== (string) $product->id) {
            throw new InvalidArgumentException('The selected variant does not belong to the product.');
        }
        if (! $product->is_serialized && ! $variant?->is_serialized) {
            throw new InvalidArgumentException('Serialized tracking is not enabled for this product.');
        }

        return DB::transaction(function () use ($serialNumbers, $productId, $variantId, $warehouse, $reference, $actorId, $note): array {
            $created = [];
            foreach ($serialNumbers as $serialNumber) {
                if (ProductSerial::query()->where('serial_number', $serialNumber)->lockForUpdate()->exists()) {
                    throw new InvalidArgumentException("Serial number {$serialNumber} already exists.");
                }

                $serial = ProductSerial::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'serial_number' => $serialNumber,
                    'status' => ProductSerial::AVAILABLE,
                    'warehouse' => $warehouse,
                    'receiving_reference' => $reference,
                    'notes' => $note,
                ]);
                $this->history($serial, 'received', null, ProductSerial::AVAILABLE, $actorId, null, $reference, $note);
                $created[] = $serial;
            }

            return $created;
        });
    }

    /** @param array<int, string> $serialNumbers */
    public function assertCartSerialsAvailable(iterable $items, array $serialNumbers): void
    {
        $serializedItems = collect($items)->filter(fn ($item): bool => (bool) ($item->variant?->is_serialized ?? $item->product?->is_serialized));
        $required = $serializedItems->sum(fn ($item): int => (int) $item->quantity);

        if ($required === 0) {
            return;
        }
        if (count($serialNumbers) !== $required || count(array_unique($serialNumbers)) !== $required) {
            throw new InvalidArgumentException('Each serialized item requires one unique serial number.');
        }

        $serials = ProductSerial::query()->whereIn('serial_number', $serialNumbers)->lockForUpdate()->get();
        if ($serials->count() !== $required || $serials->contains(fn (ProductSerial $serial): bool => $serial->status !== ProductSerial::AVAILABLE)) {
            throw new InvalidArgumentException('One or more selected serial numbers are no longer available.');
        }

        foreach ($serializedItems as $item) {
            $matches = $serials->where('product_id', (string) $item->product_id);
            if ($item->product_variant_id) {
                $matches = $matches->where('product_variant_id', $item->product_variant_id);
            }
            if ($matches->count() < (int) $item->quantity) {
                throw new InvalidArgumentException('Selected serial numbers do not match the cart product.');
            }
        }
    }

    /** @param array<int, string> $serialNumbers */
    public function sellForOrder(array $serialNumbers, int $customerId, int $orderId, ?int $actorId = null): void
    {
        foreach ($serialNumbers as $serialNumber) {
            $serial = ProductSerial::query()->where('serial_number', $serialNumber)->firstOrFail();
            $this->assignToOrder($serial, $customerId, $orderId, $actorId);
        }
    }

    public function transition(ProductSerial $serial, string $status, ?int $actorId = null, ?int $orderId = null, ?string $note = null): ProductSerial
    {
        $allowed = [
            ProductSerial::AVAILABLE => [ProductSerial::RESERVED, ProductSerial::DAMAGED, ProductSerial::LOST, ProductSerial::CANCELLED],
            ProductSerial::RESERVED => [ProductSerial::AVAILABLE, ProductSerial::SOLD, ProductSerial::CANCELLED],
            ProductSerial::SOLD => [ProductSerial::RETURNED, ProductSerial::IN_SERVICE],
            ProductSerial::RETURNED => [ProductSerial::AVAILABLE, ProductSerial::REPLACED, ProductSerial::DAMAGED],
            ProductSerial::IN_SERVICE => [ProductSerial::REPAIRED, ProductSerial::REPLACED, ProductSerial::DAMAGED],
            ProductSerial::REPAIRED => [ProductSerial::SOLD, ProductSerial::IN_SERVICE],
            ProductSerial::REPLACED => [], ProductSerial::DAMAGED => [], ProductSerial::LOST => [], ProductSerial::CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$serial->status] ?? [], true)) {
            throw new InvalidArgumentException("Cannot change serial from {$serial->status} to {$status}.");
        }

        return DB::transaction(function () use ($serial, $status, $actorId, $orderId, $note): ProductSerial {
            $locked = ProductSerial::query()->lockForUpdate()->findOrFail($serial->id);
            $previous = $locked->status;
            $locked->update(['status' => $status]);
            $this->history($locked, 'status_changed', $previous, $status, $actorId, $orderId, null, $note);

            return $locked->fresh(['product', 'variant', 'customer', 'order']);
        });
    }

    public function assignToOrder(ProductSerial $serial, int $customerId, int $orderId, ?int $actorId = null): ProductSerial
    {
        return DB::transaction(function () use ($serial, $customerId, $orderId, $actorId): ProductSerial {
            $locked = ProductSerial::query()->lockForUpdate()->findOrFail($serial->id);
            if (! in_array($locked->status, [ProductSerial::AVAILABLE, ProductSerial::RESERVED], true)) {
                throw new InvalidArgumentException('Serial is not available for sale.');
            }

            $previous = $locked->status;
            $locked->update([
                'status' => ProductSerial::SOLD,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'purchased_at' => now(),
                'warranty_start_at' => now(),
                'warranty_end_at' => now()->addYear(),
            ]);
            $this->history($locked, 'sold', $previous, ProductSerial::SOLD, $actorId, $orderId);

            return $locked;
        });
    }

    public function query(array $filters = []): Builder
    {
        return ProductSerial::query()
            ->with(['product:id,name,sku', 'variant:id,name,sku', 'customer:id,first_name,last_name,email', 'order:id,order_number'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(function (Builder $nested) use ($q): void {
                $nested->where('serial_number', 'like', "%{$q}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
            }))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['product_id'] ?? null, fn (Builder $query, string $id) => $query->where('product_id', $id))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $id) => $query->where('customer_id', $id))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $id) => $query->where('order_id', $id));
    }

    private function history(ProductSerial $serial, string $event, ?string $previous, ?string $next, ?int $actorId, ?int $orderId, ?string $reference = null, ?string $note = null): void
    {
        ProductSerialHistory::create([
            'product_serial_id' => $serial->id,
            'event' => $event,
            'previous_status' => $previous,
            'new_status' => $next,
            'actor_id' => $actorId,
            'order_id' => $orderId,
            'reference' => $reference,
            'note' => $note,
        ]);
    }
}
