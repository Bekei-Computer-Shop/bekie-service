<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Keeps the "every product has exactly one default variant" rule.
 *
 * The default is the variant flagged `product_variants.is_default`. A product
 * created without variants gets a generated one, named and SKU'd the way the
 * catalog seeders already build single-variant products ("Standard",
 * `{product sku}-DEFAULT`), with its price, stock and shipping figures copied
 * from the product so the product-level stock stays the sum of its variants.
 *
 * Callers run these inside their own transaction.
 */
class ProductDefaultVariantService
{
    public const DEFAULT_NAME = 'Standard';

    public const SKU_SUFFIX = '-DEFAULT';

    /** Matches the `variants.*.sku` request rule, so the admin can save it back unchanged. */
    private const MAX_SKU_LENGTH = 64;

    /**
     * Leaves the product with exactly one default among its live variants and
     * returns it.
     *
     * The default is, in order: `$preferredId` when it is one of this product's
     * variants, the variant already flagged, the first active variant, the first
     * variant. A product with no variants left gets a generated one, unless
     * `$createIfMissing` is false — then nothing changes and null comes back.
     */
    public function ensureDefault(Product $product, ?int $preferredId = null, bool $createIfMissing = true): ?ProductVariant
    {
        // Locked so two saves of the same product can't each pick a different default.
        $variants = $product->variants()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($variants->isEmpty()) {
            return $createIfMissing ? $this->createDefaultVariant($product) : null;
        }

        $default = ($preferredId !== null ? $variants->firstWhere('id', $preferredId) : null)
            ?? $variants->first(fn (ProductVariant $variant): bool => (bool) $variant->is_default)
            ?? $variants->first(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
            ?? $variants->first();

        foreach ($variants as $variant) {
            $isDefault = $variant->is($default);

            if ((bool) $variant->is_default !== $isDefault) {
                $variant->forceFill(['is_default' => $isDefault])->save();
            }
        }

        return $default;
    }

    /**
     * Creates the generated default variant for a product that has none.
     */
    public function createDefaultVariant(Product $product): ProductVariant
    {
        [$sku, $slug] = $this->uniqueSkuAndSlug((string) $product->sku);
        $stock = (int) $product->stock_quantity;

        return $product->variants()->create([
            'name' => self::DEFAULT_NAME,
            'slug' => $slug,
            'sku' => $sku,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'cost_price' => $product->cost_price,
            'stock_quantity' => $stock,
            'min_stock_alert' => $product->min_stock_alert ?? 0,
            'track_inventory' => $product->track_inventory ?? true,
            'is_serialized' => (bool) $product->is_serialized,
            'in_stock' => $stock > 0,
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'attributes' => [],
            'is_default' => true,
            // Always active: whether the product is on sale is the product's own
            // `is_active`. Copying a draft product's `false` here would leave the
            // variant hidden once the product is published.
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * `{sku}-DEFAULT`, then `{sku}-DEFAULT-2`, … until neither the SKU nor its
     * slug is taken. Both columns are unique across soft-deleted variants too.
     *
     * @return array{0: string, 1: string}
     */
    private function uniqueSkuAndSlug(string $productSku): array
    {
        $attempt = 1;

        while (true) {
            $tail = self::SKU_SUFFIX.($attempt > 1 ? "-{$attempt}" : '');
            $sku = substr($productSku, 0, self::MAX_SKU_LENGTH - strlen($tail)).$tail;
            $slug = Str::slug($sku);

            $taken = ProductVariant::withTrashed()
                ->where(fn ($query) => $query->where('sku', $sku)->orWhere('slug', $slug))
                ->exists();

            if (! $taken) {
                return [$sku, $slug];
            }

            $attempt++;
        }
    }
}
