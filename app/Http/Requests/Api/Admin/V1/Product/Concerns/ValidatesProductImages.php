<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\V1\Product\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared rules for the nested product image gallery, used by both the store and
 * update requests so the two can't drift apart.
 *
 * Rows are references to already-uploaded files: the client uploads through
 * POST /admin/media (Cloudinary) and sends the returned URL here. The product
 * endpoints never accept a binary upload.
 */
trait ValidatesProductImages
{
    /** Mirrors the enum on product_images.type. */
    public const IMAGE_TYPES = ['thumbnail', 'gallery', 'banner', 'zoom'];

    /** How many gallery rows one product may carry. */
    public const MAX_IMAGES = 12;

    /**
     * @return array<string, array<mixed>>
     */
    protected function productImageRules(): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            // Capped at 255 to match the varchar column — a longer URL would
            // pass validation and then fail at the driver.
            'images.*.image' => ['required_with:images', 'string', 'max:255'],
            'images.*.disk' => ['nullable', 'string', 'max:50'],
            'images.*.mime_type' => ['nullable', 'string', 'max:100'],
            'images.*.file_size' => ['nullable', 'integer', 'min:0'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.title' => ['nullable', 'string', 'max:255'],
            'images.*.type' => ['nullable', Rule::in(self::IMAGE_TYPES)],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.is_active' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * At most one row may claim `is_primary`.
     *
     * The controller derives products.thumbnail from the primary row, so two
     * of them would make the thumbnail depend on row order — a silent, hard to
     * reproduce result. Rejecting it up front is cheaper than picking a winner.
     */
    protected function validateSinglePrimaryImage(Validator $validator): void
    {
        $images = $this->input('images');

        if (! is_array($images)) {
            return;
        }

        $primaries = [];
        foreach ($images as $index => $image) {
            if (is_array($image) && filter_var($image['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $primaries[] = $index;
            }
        }

        if (count($primaries) > 1) {
            $validator->errors()->add(
                'images.'.$primaries[1].'.is_primary',
                'Only one image can be the primary image.'
            );
        }
    }
}
