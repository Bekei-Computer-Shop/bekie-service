<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Resources\Api\Admin\V1\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->when($request->filled('position'), fn ($query) => $query->where('position', $request->input('position')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            // Carousel order, not recency: the admin list is a reorder surface,
            // so it has to show banners in the sequence the storefront plays
            // them. `ordered()` sorts by sort_order, then id as a tiebreak.
            ->ordered()
            ->paginate(15);

        return $this->success(BannerResource::collection($banners));
    }

    public function show(Banner $banner): JsonResponse
    {
        return $this->success(new BannerResource($banner));
    }

    public function store(Request $request): JsonResponse
    {
        $banner = Banner::create($this->validatedData($request));

        return $this->created(new BannerResource($banner));
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $banner->update($this->validatedData($request));

        return $this->success(new BannerResource($banner->fresh()));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return $this->noContent();
    }

    public function toggleStatus(Banner $banner): JsonResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return $this->success(new BannerResource($banner->fresh()));
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'image_desktop' => ['nullable', 'string', 'max:255'],
            'image_mobile' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'position' => ['sometimes', 'string', 'max:50'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);
    }
}
