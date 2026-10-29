<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Product\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Checks on the nested `variants` array that the column rules can't express,
 * shared by the store and update requests. On store there is no route product,
 * so every row is a new variant.
 */
trait ValidatesVariantRows
{
    /**
     * Variant `id`, `sku`, `slug` and `barcode` checks. The database keeps those
     * three unique across every variant, soft-deleted ones included, so a clash
     * used to surface as a raw 500; here it is a 422 on the row that caused it.
     *
     * A row is checked against everyone except the variant it edits: the one
     * named by its `id`, or — for clients that send none — the one with the
     * same SKU.
     */
    private function validateVariantRows($validator): void
    {
        $rows = $this->input('variants');

        if (! is_array($rows)) {
            return;
        }

        /** @var Product|null $product */
        $product = $this->route('product');
        $owned = $product ? $product->variants()->get(['id', 'sku'])->keyBy('id') : collect();
        $skus = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($id !== null && ! $owned->has($id)) {
                $validator->errors()->add("variants.{$index}.id", 'This variant does not belong to the product.');

                continue;
            }

            $sku = (string) ($row['sku'] ?? '');
            $targetId = $id ?? $owned->firstWhere('sku', $sku)?->id;

            // A new row's slug comes from its name when none is sent, exactly as
            // ProductController::createVariant() derives it.
            $slug = ! empty($row['slug'])
                ? Str::slug((string) $row['slug'])
                : ($targetId === null ? Str::slug((string) ($row['name'] ?? '')) : '');
            $barcode = (string) ($row['barcode'] ?? '');

            if ($sku !== '') {
                if (in_array($sku, $skus, true)) {
                    $validator->errors()->add("variants.{$index}.sku", 'This SKU appears on more than one variant.');
                }
                $skus[] = $sku;
            }

            foreach (['sku' => $sku, 'slug' => $slug, 'barcode' => $barcode] as $column => $value) {
                if ($value === '') {
                    continue;
                }

                $taken = ProductVariant::withTrashed()
                    ->where($column, $value)
                    ->when($targetId !== null, fn ($query) => $query->whereKeyNot($targetId))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add("variants.{$index}.{$column}", "This variant {$column} is already used by another variant.");
                }
            }
        }
    }

    /**
     * At most one row may be marked `is_default`. None is fine: the first
     * active variant becomes the default (see ProductDefaultVariantService).
     */
    private function validateSingleDefaultVariant($validator): void
    {
        $rows = $this->input('variants');

        if (! is_array($rows)) {
            return;
        }

        $seen = false;

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if ($seen) {
                $validator->errors()->add("variants.{$index}.is_default", 'Only one variant can be the default.');
            }

            $seen = true;
        }
    }
}
