<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\ContentPageResource;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;

class ContentPageController extends BaseApiController
{
    public function show(string $slug): JsonResponse
    {
        $page = ContentItem::query()
            ->published()
            ->where('type', 'page')
            ->where('slug', $slug)
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->first();

        if ($page === null) {
            return $this->error('Content page not found.', 404);
        }

        return $this->success(new ContentPageResource($page));
    }
}
