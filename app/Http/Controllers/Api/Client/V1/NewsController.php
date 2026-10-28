<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\NewsResource;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = ContentItem::query()
            ->published()
            ->where('type', 'news')
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->when(
                isset($validated['category']),
                fn ($query) => $query->where('category', $validated['category']),
            );

        $paginator = $query
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return $this->success([
            'items' => NewsResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
