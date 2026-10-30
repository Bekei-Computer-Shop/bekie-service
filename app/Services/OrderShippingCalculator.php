<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Prices shipping for an admin-built order from its lines. The rules are the
 * ones checkout uses: the order's weight is each line's variant weight (or its
 * product's) times the quantity, and the method decides from that weight
 * whether it can carry the order (ShippingMethod::supportsWeight) and what it
 * charges (ShippingMethod::calculateCost). The fee is never taken from the
 * request.
 */
class OrderShippingCalculator
{
    /**
     * @param  Collection<int, array{product: Product, quantity: int, variant_id: int|null}>  $lines
     */
    public function weight(Collection $lines): float
    {
        $variants = ProductVariant::query()
            ->findMany($lines->pluck('variant_id')->filter()->unique()->all())
            ->keyBy('id');

        return round((float) $lines->sum(function (array $line) use ($variants): float {
            $variant = $line['variant_id'] ? $variants->get($line['variant_id']) : null;

            return $line['quantity'] * (float) ($variant?->weight ?? $line['product']->weight ?? 0);
        }), 2);
    }

    /**
     * Every active method with what it would charge for this weight, in the
     * order the store lists them. A method the weight is outside of is still
     * listed, marked unavailable, so the form can say why it cannot be picked.
     *
     * @return list<array{id: int, name: string, code: string, description: string|null, estimated_delivery: string, fee: string, available: bool}>
     */
    public function options(float $weight): array
    {
        return ShippingMethod::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (ShippingMethod $method): array => [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'description' => $method->description,
                'estimated_delivery' => $method->estimated_delivery,
                'fee' => number_format($method->calculateCost($weight), 2, '.', ''),
                'available' => $method->supportsWeight($weight),
            ])
            ->values()
            ->all();
    }

    /**
     * The chosen method and its fee for this weight. Throws a validation error
     * on `shipping_method_id` when the method is inactive, deleted or cannot
     * carry the weight, so a stale form selection is a 422, not a bad order.
     *
     * @return array{method: ShippingMethod, fee: float}
     */
    public function quote(int $shippingMethodId, float $weight): array
    {
        $method = ShippingMethod::query()->active()->find($shippingMethodId);

        if (! $method) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['The selected shipping method is not available.'],
            ]);
        }

        if (! $method->supportsWeight($weight)) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ["{$method->name} cannot ship an order weighing {$weight} kg."],
            ]);
        }

        return ['method' => $method, 'fee' => $method->calculateCost($weight)];
    }
}
