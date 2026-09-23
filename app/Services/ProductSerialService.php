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
    public function receive(array $serialNumbers, string $productId, ?int $variantId = null, ?string $warehouse = null, ?string $reference = null, ?int $actorId = null, ?string $note = null, ?int $receivingQuantity = null): array
    {
        $serialNumbers = array_values(array_filter(array_map(
            static fn (string $serial): string => trim($serial),
            $serialNumbers,
        ), static fn (string $serial): bool => $serial !== ''));

        $normalized = array_map('strtolower', $serialNumbers);
        if (count($serialNumbers) === 0 || count($normalized) !== count(array_unique($normalized))) {
            throw new InvalidArgumentException('At least one unique serial number is required.');
        }
        if ($receivingQuantity !== null && $receivingQuantity !== count($serialNumbers)) {
            throw new InvalidArgumentException('Receiving quantity must match the serial number count.');
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
                if (ProductSerial::query()->whereRaw('LOWER(serial_number) = ?', [strtolower($serialNumber)])->lockForUpdate()->exists()) {
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
        $normalized = array_map('strtolower', $serialNumbers);
        if (count($serialNumbers) !== $required || count($normalized) !== count(array_unique($normalized))) {
            throw new InvalidArgumentException('Each serialized item requires one unique serial number.');
        }

        $serials = ProductSerial::query()
            ->whereIn(DB::raw('LOWER(serial_number)'), $normalized)
            ->lockForUpdate()
            ->get();
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
        DB::transaction(function () use ($serialNumbers, $customerId, $orderId, $actorId): void {
            $normalized = array_map('strtolower', $serialNumbers);
            $serials = ProductSerial::query()
                ->where('order_id', $orderId)
                ->whereIn(DB::raw('LOWER(serial_number)'), $normalized)
                ->lockForUpdate()
                ->get();

            if ($serials->count() !== count($normalized) || $serials->contains(fn (ProductSerial $serial): bool => $serial->status !== ProductSerial::RESERVED || (int) $serial->customer_id !== $customerId)) {
                throw new InvalidArgumentException('Serials must be reserved for this order before sale.');
            }

            $serials->each(function (ProductSerial $serial) use ($orderId, $actorId): void {
                $serial->update([
                    'status' => ProductSerial::SOLD,
                    'purchased_at' => now(),
                    'warranty_start_at' => now(),
                    'warranty_end_at' => now()->addYear(),
                ]);
                $this->history($serial, 'sold', ProductSerial::RESERVED, ProductSerial::SOLD, $actorId, $orderId);
            });
        });
    }

    /** @param array<int, string> $serialNumbers */
    public function reserveForOrder(array $serialNumbers, int $customerId, int $orderId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($serialNumbers, $customerId, $orderId, $actorId): void {
            $normalized = array_map('strtolower', $serialNumbers);
            $serials = ProductSerial::query()
                ->whereIn(DB::raw('LOWER(serial_number)'), $normalized)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ProductSerial $serial): string => strtolower($serial->serial_number));

            foreach ($normalized as $serialNumber) {
                $serial = $serials->get($serialNumber);
                if (! $serial) {
                    throw new InvalidArgumentException("Serial number {$serialNumber} was not found.");
                }
                if ($serial->status !== ProductSerial::AVAILABLE) {
                    throw new InvalidArgumentException('Serial is not available for reservation.');
                }

                $serial->update([
                    'status' => ProductSerial::RESERVED,
                    'customer_id' => $customerId,
                    'order_id' => $orderId,
                ]);
                $this->history($serial, 'reserved', ProductSerial::AVAILABLE, ProductSerial::RESERVED, $actorId, $orderId);
            }
        });
    }

    public function finalizeOrder(int $orderId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($orderId, $actorId): void {
            ProductSerial::query()->where('order_id', $orderId)->lockForUpdate()->get()->each(function (ProductSerial $serial) use ($orderId, $actorId): void {
                if ($serial->status !== ProductSerial::RESERVED) {
                    return;
                }

                $serial->update([
                    'status' => ProductSerial::SOLD,
                    'purchased_at' => now(),
                    'warranty_start_at' => now(),
                    'warranty_end_at' => now()->addYear(),
                ]);
                $this->history($serial, 'sold', ProductSerial::RESERVED, ProductSerial::SOLD, $actorId, $orderId);
            });
        });
    }

    public function releaseOrder(int $orderId, ?int $actorId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($orderId, $actorId, $note): void {
            ProductSerial::query()->where('order_id', $orderId)->lockForUpdate()->get()->each(function (ProductSerial $serial) use ($orderId, $actorId, $note): void {
                if ($serial->status !== ProductSerial::RESERVED) {
                    return;
                }

                $serial->update([
                    'status' => ProductSerial::AVAILABLE,
                    'customer_id' => null,
                    'order_id' => null,
                ]);
                $this->history($serial, 'reservation_released', ProductSerial::RESERVED, ProductSerial::AVAILABLE, $actorId, $orderId, null, $note);
            });
        });
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
            if ($locked->status !== ProductSerial::RESERVED) {
                throw new InvalidArgumentException('Serial must be reserved before sale.');
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
                $nested->where('serial_number', 'ilike', "%{$q}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
            }))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['product_id'] ?? null, fn (Builder $query, string $id) => $query->where('product_id', $id))
            ->when($filters['product_variant_id'] ?? null, fn (Builder $query, int $id) => $query->where('product_variant_id', $id))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $id) => $query->where('customer_id', $id))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int $id) => $query->where('order_id', $id))
            ->when($filters['received_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['received_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    public function getExpiringWarranties(int $daysUntilExpiry = 30): \Illuminate\Support\Collection
    {
        return ProductSerial::query()
            ->where('status', ProductSerial::SOLD)
            ->whereBetween('warranty_end_at', [now(), now()->addDays($daysUntilExpiry)])
            ->with(['product:id,name,sku', 'customer:id,first_name,last_name,email'])
            ->orderBy('warranty_end_at')
            ->get();
    }

    public function getCustomerWarrantyStats(int $customerId): array
    {
        return [
            'total_units' => ProductSerial::query()->where('customer_id', $customerId)->count(),
            'active_warranty' => ProductSerial::query()
                ->where('customer_id', $customerId)
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '>', now())
                ->count(),
            'expiring_soon' => ProductSerial::query()
                ->where('customer_id', $customerId)
                ->where('status', ProductSerial::SOLD)
                ->whereBetween('warranty_end_at', [now(), now()->addDays(30)])
                ->count(),
            'expired_warranty' => ProductSerial::query()
                ->where('customer_id', $customerId)
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '<=', now())
                ->count(),
        ];
    }

    private function assignLockedSerial(ProductSerial $serial, int $customerId, int $orderId, ?int $actorId = null): ProductSerial
    {
        if (! in_array($serial->status, [ProductSerial::AVAILABLE, ProductSerial::RESERVED], true)) {
            throw new InvalidArgumentException('Serial is not available for sale.');
        }

        $previous = $serial->status;
        $serial->update([
            'status' => ProductSerial::SOLD,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'purchased_at' => now(),
            'warranty_start_at' => now(),
            'warranty_end_at' => now()->addYear(),
        ]);
        $this->history($serial, 'sold', $previous, ProductSerial::SOLD, $actorId, $orderId);

        return $serial;
    }

    public function validateWarranty(ProductSerial $serial): array
    {
        if (! $serial->warranty_end_at) {
            return [
                'valid' => false,
                'reason' => 'no_warranty',
                'message' => 'This product does not have warranty coverage.',
            ];
        }

        if ($serial->warranty_end_at->isPast()) {
            return [
                'valid' => false,
                'reason' => 'expired',
                'message' => "Warranty expired on {$serial->warranty_end_at->format('Y-m-d')}.",
                'expired_at' => $serial->warranty_end_at->toIso8601String(),
            ];
        }

        $daysRemaining = now()->diffInDays($serial->warranty_end_at, absolute: false);

        return [
            'valid' => true,
            'reason' => 'active',
            'message' => "Warranty active for {$daysRemaining} more days.",
            'days_remaining' => $daysRemaining,
            'expires_at' => $serial->warranty_end_at->toIso8601String(),
        ];
    }

    public function getWarrantyStats(): array
    {
        return [
            'total_sold' => ProductSerial::query()->where('status', ProductSerial::SOLD)->count(),
            'active_warranty' => ProductSerial::query()
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '>', now())
                ->count(),
            'expired_warranty' => ProductSerial::query()
                ->where('status', ProductSerial::SOLD)
                ->where('warranty_end_at', '<=', now())
                ->count(),
            'no_warranty' => ProductSerial::query()
                ->where('status', ProductSerial::SOLD)
                ->whereNull('warranty_end_at')
                ->count(),
            'expiring_soon' => ProductSerial::query()
                ->where('status', ProductSerial::SOLD)
                ->whereBetween('warranty_end_at', [now(), now()->addDays(30)])
                ->count(),
        ];
    }

    public function setWarrantyPeriod(ProductSerial $serial, int $days, ?int $actorId = null): ProductSerial
    {
        return DB::transaction(function () use ($serial, $days, $actorId): ProductSerial {
            $locked = ProductSerial::query()->lockForUpdate()->findOrFail($serial->id);
            $startDate = $locked->warranty_start_at ?? now();
            $endDate = $startDate->copy()->addDays($days);

            $locked->update([
                'warranty_start_at' => $startDate,
                'warranty_end_at' => $endDate,
            ]);
            $this->history($locked, 'warranty_set', null, null, $actorId, null, null, "Warranty set for {$days} days");

            return $locked->fresh();
        });
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
