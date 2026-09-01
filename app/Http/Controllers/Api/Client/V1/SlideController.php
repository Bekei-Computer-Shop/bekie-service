<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\SlideResource;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront slides (homepage carousel banners).
 *
 * The admin panel manages these through the `Banner` model ("Content →
 * Homepage Slides"); this endpoint is the public, read-only view of the same
 * rows. Only banners that are currently visible are returned: active, inside
 * their scheduling window and not soft-deleted, ordered by `sort_order` so the
 * carousel plays the sequence the admin configured.
 */
class SlideController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $slides = Banner::query()
            ->active()
            ->visible()
            ->when(
                $request->filled('position'),
                fn (Builder $query) => $query->position((string) $request->query('position'))
            )
            ->ordered()
            ->get();

        return $this->success(SlideResource::collection($slides));
    }
}