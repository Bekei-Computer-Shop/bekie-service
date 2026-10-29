<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Product;

use App\Http\Requests\Api\Admin\V1\Product\Concerns\ValidatesProductImages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductImages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product|null $target */
        $target = $this->route('product');
        $targetId = $target?->getKey();

        return [
            // Nullable: a product can be removed from its category entirely
            // (the admin category picker's "uncheck to remove" flow does this).
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists((new Category)->getTable(), 'id')->whereNull('deleted_at')],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists((new Brand)->getTable(), 'id')->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('products', 'sku')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($targetId)->whereNull('deleted_at')],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'min_stock_alert' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'track_inventory' => ['sometimes', 'boolean'],
            'is_serialized' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_digital' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],

            // Image gallery. Sending the key replaces the whole set; omitting
            // it leaves the existing gallery untouched.
            ...$this->productImageRules(),

            // Promotions applied to this product. Validated against `coupons`:
            // the admin promotions API is coupon-backed (PromotionController).
            'promotion_ids' => ['sometimes', 'array'],
            'promotion_ids.*' => ['integer', Rule::exists('coupons', 'id')->whereNull('deleted_at')->where('target_type', Coupon::TARGET_PRODUCTS)],

            // Variant array. When `replace_variants` is omitted or true, the
            // submitted set replaces the existing set. When false, the
            // controller syncs it: a row with an `id` edits that variant (its
            // SKU included), a row without one is matched by SKU or created.
            // `remove_missing_variants` additionally removes the product's
            // variants that the array leaves out.
            'replace_variants' => ['nullable', 'boolean'],
            'remove_missing_variants' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:191'],
            'variants.*.slug' => ['nullable', 'string', 'max:191'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'variants.*.min_stock_alert' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'variants.*.track_inventory' => ['nullable', 'boolean'],
            'variants.*.is_serialized' => ['nullable', 'boolean'],
            'variants.*.in_stock' => ['nullable', 'boolean'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.length' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.width' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.height' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.image' => ['nullable', 'string', 'max:255'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must be lowercase letters, digits, and dashes.',
            'sku.regex' => 'SKU may only contain letters, digits, dot, underscore, and dash.',
            'variants.*.sku.regex' => 'Variant SKU may only contain letters, digits, dot, underscore, and dash.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $price = $this->input('price');
            $sale = $this->input('sale_price');
            if ($price !== null && $sale !== null && (float) $sale > (float) $price) {
                $v->errors()->add('sale_price', 'Sale price must be less than or equal to price.');
            }

            $this->validateSinglePrimaryImage($v);
            $this->validateVariantRows($v);
        });
    }

    /**
     * Variant `id`, `sku`, `slug` and `barcode` checks the column rules can't
     * express. The database keeps those three unique across every variant,
     * soft-deleted ones included, so a clash used to surface as a raw 500; here
     * it is a 422 on the row that caused it.
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
}
