<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductSerialHistory;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\ProductPromotionService;
use App\Services\TaxCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds one demo order the way checkout would have priced it on the day it
 * was placed, so seeded totals, discounts and usage counters agree with the
 * live calculation:
 *
 *  - the clock is moved to the placement time while pricing, so only the
 *    promotions running that day apply (ProductPromotionService, unchanged);
 *  - a coupon given by code is priced with couponCodeDiscount() and left out
 *    of the product pricing, exactly as OrderController::store does;
 *  - tax comes from TaxCalculator and shipping from the method's own rate;
 *  - every promotion applied gets one usage row and one `used_count`, so the
 *    counter always equals the usage rows behind it.
 *
 * Promotions an operator created are never applied (only `$promotionCodes`
 * are eligible), so demo orders cannot move a real campaign's counters.
 */
final class DemoOrderBuilder
{
    /** @var array<int, true> ids of coupons demo orders may apply */
    private array $eligibleCouponIds;

    /** @var list<int> */
    private array $ineligibleCouponIds;

    /**
     * @param  list<string>  $promotionCodes  coupon codes demo orders may apply
     */
    public function __construct(
        private readonly ProductPromotionService $promotions,
        private readonly TaxCalculator $taxes,
        array $promotionCodes,
    ) {
        $ids = Coupon::query()->withTrashed()->pluck('code', 'id');
        $this->eligibleCouponIds = $ids->filter(fn (string $code): bool => in_array($code, $promotionCodes, true))
            ->map(fn (): bool => true)
            ->all();
        $this->ineligibleCouponIds = $ids->keys()
            ->reject(fn (int $id): bool => isset($this->eligibleCouponIds[$id]))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     tag: string,
     *     order_number: string,
     *     customer: User,
     *     placed_at: \DateTimeInterface,
     *     status: string,
     *     payment_method: string,
     *     lines: list<array{variant: ProductVariant, quantity: int}>,
     *     coupon?: ?Coupon,
     *     skip_promotions?: list<string>,
     *     notes?: ?string,
     * }  $spec
     */
    public function create(array $spec): Order
    {
        $placedAt = Carbon::instance($spec['placed_at']);
        $previousNow = Carbon::getTestNow();
        $previousImmutableNow = CarbonImmutable::getTestNow();

        Carbon::setTestNow($placedAt);
        CarbonImmutable::setTestNow($placedAt->toImmutable());

        try {
            return $this->build($spec, $placedAt);
        } finally {
            Carbon::setTestNow($previousNow);
            CarbonImmutable::setTestNow($previousImmutableNow);
        }
    }

    private function build(array $spec, Carbon $placedAt): Order
    {
        /** @var User $customer */
        $customer = $spec['customer'];
        $status = $spec['status'];
        $coupon = $spec['coupon'] ?? null;
        $address = $this->addressFor($customer);
        $country = $address?->country ?? 'Cambodia';

        $lines = collect($spec['lines'])->values()->map(function (array $line): array {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $product = $variant->product;

            return [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) $line['quantity'],
                // Same precedence as ProductPromotionService::currentUnitPrice().
                'unit_price' => (float) ($variant->sale_price ?? $product->sale_price ?? $variant->price ?? $product->price),
                'variant_id' => (int) $variant->id,
            ];
        });

        $except = $this->ineligibleCouponIds;
        foreach ($spec['skip_promotions'] ?? [] as $code) {
            $skipId = Coupon::query()->withTrashed()->where('code', $code)->value('id');
            if ($skipId !== null) {
                $except[] = (int) $skipId;
            }
        }
        if ($coupon !== null) {
            $except[] = (int) $coupon->id;
        }

        $priced = $this->promotions->priceLines($lines, (int) $customer->id, array_values(array_unique($except)));
        $subtotal = round((float) $priced->sum('subtotal'), 2);

        $couponDiscount = 0.0;
        if ($coupon !== null && isset($this->eligibleCouponIds[$coupon->id])) {
            $couponDiscount = $this->promotions->couponCodeDiscount($coupon->fresh(), (int) $customer->id, $subtotal);
        }
        if ($couponDiscount <= 0) {
            $coupon = null;
        }

        $discountTotal = round(min($subtotal, $couponDiscount + (float) $priced->sum('discount')), 2);
        $tax = $this->taxes->calculate($subtotal, $discountTotal, $country);
        $weight = (float) $lines->sum(fn (array $line): float => $line['quantity'] * (float) ($line['variant']->weight ?? $line['product']->weight ?? 0));
        $shippingMethod = $this->shippingMethodFor($weight);
        $shippingTotal = $shippingMethod?->calculateCost($weight) ?? 0.0;
        $paymentStatus = $this->paymentStatus($status, $spec['payment_method']);

        $order = Order::create([
            // Set here: the RoutesByUuid creating hook does not run while
            // DatabaseSeeder mutes model events.
            'uuid' => (string) Str::uuid(),
            'user_id' => $customer->id,
            'address_id' => $address?->id,
            'order_number' => $spec['order_number'],
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'tax_total' => $tax['tax_total'],
            'shipping_total' => $shippingTotal,
            'grand_total' => round($tax['grand_total'] + $shippingTotal, 2),
            'currency' => 'USD',
            'payment_method' => $spec['payment_method'],
            'payment_status' => $paymentStatus,
            'transaction_id' => $spec['payment_method'] === 'aba_payway' && $paymentStatus !== 'pending'
                ? 'ABA-'.strtoupper(substr(md5($spec['order_number']), 0, 12))
                : null,
            'status' => $status,
            'notes' => $spec['notes'] ?? null,
            'shipping_status' => $this->shippingStatus($status),
            'tracking_number' => in_array($status, ['processing', 'completed'], true)
                ? 'TRK'.substr(sprintf('%u', crc32($spec['order_number'])), 0, 9)
                : null,
            'shipping_provider' => $shippingMethod?->name,
            'customer_snapshot' => [
                'user_id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'address_snapshot' => $address?->only([
                'full_name', 'phone', 'address_line_1', 'address_line_2',
                'city', 'state', 'postal_code', 'country',
            ]),
            'metadata' => ['seeded_by' => $spec['tag']],
            ...$this->lifecycleTimestamps($status, $placedAt),
        ]);
        $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->saveQuietly();

        foreach ($lines as $index => $line) {
            $snapshot = $this->promotions->snapshot($priced[$index]);
            $variant = $line['variant'];

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product']->id,
                'product_variant_id' => $variant->id,
                'quantity' => $line['quantity'],
                'unit_price' => $priced[$index]['unit_price'],
                'sale_price' => $variant->sale_price ?? $line['product']->sale_price,
                'cost_price' => $variant->cost_price ?? $line['product']->cost_price,
                'subtotal' => $priced[$index]['subtotal'],
                'discount' => $priced[$index]['discount'],
                'tax' => 0,
                'total' => $priced[$index]['total'],
                'product_name' => $line['product']->name,
                'product_sku' => $variant->sku,
                'variant_name' => $variant->name,
                'variant_attributes' => $variant->attributes,
                'quantity_shipped' => in_array($status, ['processing', 'completed'], true) ? $line['quantity'] : 0,
                'quantity_refunded' => 0,
                'status' => $this->itemStatus($status),
                'metadata' => $snapshot ? ['promotion' => $snapshot] : null,
            ]);

            $this->seedSerials($order, $line, $status, $placedAt);
        }

        $this->recordPromotionUsage($order, $priced, $spec['tag'], $placedAt);
        if ($coupon !== null) {
            $this->recordUsage($coupon, $order, $couponDiscount, $spec['tag'], 'coupon_code', $placedAt);
        }

        if ($spec['payment_method'] === 'aba_payway') {
            $this->seedPayment($order, $paymentStatus, $placedAt);
        }

        return $order;
    }

    private function addressFor(User $customer): ?Address
    {
        return $customer->addresses()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function shippingMethodFor(float $weight): ?ShippingMethod
    {
        return ShippingMethod::query()
            ->active()
            ->where('type', '!=', 'pickup')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (ShippingMethod $method): bool => $method->supportsWeight($weight));
    }

    /**
     * One usage row and one counter step per promotion per order, however
     * many lines it discounted — ProductPromotionService::recordUsage().
     *
     * @param  Collection<int, array{discount: float, promotion: ?Coupon}>  $priced
     */
    private function recordPromotionUsage(Order $order, Collection $priced, string $tag, Carbon $placedAt): void
    {
        $priced
            ->filter(fn (array $line): bool => $line['promotion'] !== null)
            ->groupBy(fn (array $line): int => $line['promotion']->id)
            ->each(fn (Collection $lines) => $this->recordUsage(
                $lines->first()['promotion'],
                $order,
                round((float) $lines->sum('discount'), 2),
                $tag,
                'product_promotion',
                $placedAt,
            ));
    }

    private function recordUsage(Coupon $coupon, Order $order, float $discount, string $tag, string $source, Carbon $placedAt): void
    {
        // Same guard as checkout: the counter never passes its limit. Pricing
        // already checked validity against the current count, so this only
        // fails if two lines raced for the last use.
        $updated = Coupon::query()
            ->whereKey($coupon->id)
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');

        if ($updated !== 1) {
            return;
        }

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $discount,
            'used_at' => $placedAt,
            'metadata' => ['source' => $source, 'seeded_by' => $tag],
        ]);
    }

    private function seedPayment(Order $order, string $paymentStatus, Carbon $placedAt): void
    {
        Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'payment_reference' => 'PAY-'.$order->order_number,
            'provider' => 'aba_payway',
            'provider_payment_id' => $order->transaction_id,
            'amount' => $order->grand_total,
            'currency' => 'USD',
            'status' => $paymentStatus,
            'payment_method' => 'aba_payway',
            'metadata' => ['seeded_by' => $order->metadata['seeded_by'] ?? null],
            'paid_at' => $paymentStatus === 'paid' ? $placedAt->copy()->addMinutes(3) : null,
            'failed_at' => $paymentStatus === 'failed' ? $placedAt->copy()->addMinutes(15) : null,
        ]);
    }

    /**
     * Serialized units go out with the order: reserved while it is pending,
     * sold (with a one-year warranty, as ProductSerialService writes it) once
     * it ships. They are created as already-shipped units rather than taken
     * from the warehouse pool, so the pool still matches on-hand stock.
     *
     * @param  array{product: Product, variant: ProductVariant, quantity: int}  $line
     */
    private function seedSerials(Order $order, array $line, string $status, Carbon $placedAt): void
    {
        $serialized = (bool) ($line['variant']->is_serialized || $line['product']->is_serialized);
        if (! $serialized || $status === 'cancelled') {
            return;
        }

        $sold = in_array($status, ['processing', 'completed'], true);
        $soldAt = $placedAt->copy()->addHours(2);

        for ($unit = 1; $unit <= $line['quantity']; $unit++) {
            $serial = ProductSerial::create([
                'product_id' => $line['product']->id,
                'product_variant_id' => $line['variant']->id,
                'serial_number' => sprintf('%s-%s-%02d', $line['variant']->sku, $order->order_number, $unit),
                'status' => $sold ? ProductSerial::SOLD : ProductSerial::RESERVED,
                'warehouse' => 'Phnom Penh Main',
                'receiving_reference' => DemoData::ORDER_SERIAL_REFERENCE,
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'purchased_at' => $sold ? $soldAt : null,
                'warranty_start_at' => $sold ? $soldAt : null,
                'warranty_end_at' => $sold ? $soldAt->copy()->addYear() : null,
                'notes' => 'Seeded with a demo order.',
            ]);

            $events = [['received', null, ProductSerial::AVAILABLE, null], ['reserved', ProductSerial::AVAILABLE, ProductSerial::RESERVED, $order->id]];
            if ($sold) {
                $events[] = ['sold', ProductSerial::RESERVED, ProductSerial::SOLD, $order->id];
            }

            foreach ($events as [$event, $previous, $next, $orderId]) {
                ProductSerialHistory::create([
                    'product_serial_id' => $serial->id,
                    'event' => $event,
                    'previous_status' => $previous,
                    'new_status' => $next,
                    'order_id' => $orderId,
                    'reference' => DemoData::ORDER_SERIAL_REFERENCE,
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);
            }
        }
    }

    private function paymentStatus(string $status, string $method): string
    {
        return match ($status) {
            'pending' => 'pending',
            // A card payment that never cleared vs a COD order nobody paid for.
            'cancelled' => $method === 'aba_payway' ? 'failed' : 'pending',
            default => 'paid',
        };
    }

    private function shippingStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'shipped',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function itemStatus(string $status): string
    {
        return match ($status) {
            'processing' => 'shipped',
            'completed' => 'delivered',
            default => 'pending',
        };
    }

    /** @return array<string, Carbon> */
    private function lifecycleTimestamps(string $status, Carbon $placedAt): array
    {
        $stamps = [];

        if (in_array($status, ['processing', 'completed'], true)) {
            $stamps['paid_at'] = $placedAt->copy()->addMinutes(12);
            $stamps['shipped_at'] = $placedAt->copy()->addDay();
        }
        if ($status === 'completed') {
            $stamps['delivered_at'] = $placedAt->copy()->addDays(3);
        }
        if ($status === 'cancelled') {
            $stamps['cancelled_at'] = $placedAt->copy()->addHours(5);
        }

        return $stamps;
    }
}
