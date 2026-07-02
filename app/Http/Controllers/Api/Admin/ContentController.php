<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Requests\Admin\StoreContentRequest;
use App\Http\Requests\Admin\UpdateContentRequest;
use App\Http\Resources\Admin\ContentItemResource;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $content = ContentItem::with('author')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(15);

        return $this->success(ContentItemResource::collection($content));
    }

    public function show(ContentItem $item): JsonResponse
    {
        return $this->success(new ContentItemResource($item->load('author')));
    }

    public function store(StoreContentRequest $request): JsonResponse
    {
        $content = ContentItem::create(array_merge($request->validated(), [
            'author_id' => $request->user()->id,
        ]));

        return $this->created(new ContentItemResource($content));
    }

    public function update(UpdateContentRequest $request, ContentItem $item): JsonResponse
    {
        $item->update($request->validated());

        return $this->success(new ContentItemResource($item));
    }

    public function destroy(ContentItem $item): JsonResponse
    {
        $item->delete();

        return $this->noContent();
    }

    public function publish(ContentItem $item): JsonResponse
    {
        $item->update(['status' => 'published', 'published_at' => now()]);

        return $this->success(new ContentItemResource($item));
    }

    public function archive(ContentItem $item): JsonResponse
    {
        $item->update(['status' => 'archived']);

        return $this->success(new ContentItemResource($item));
    }
}
