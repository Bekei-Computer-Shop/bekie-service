<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Resources\Api\Client\V1\NewsDetailResource;
use App\Http\Resources\Api\Client\V1\NewsResource;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends BaseApiController
{
    /** How many other articles the detail page suggests. */
    private const RELATED_LIMIT = 4;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = $this->visibleNews()
            ->when(
                isset($validated['category']),
                fn ($query) => $query->where('category', $validated['category']),
            );

        $paginator = $this->newestFirst($query)
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

    /**
     * One published article, by id or slug.
     *
     * Drafts, archived and scheduled articles answer 404 just like a missing
     * one, so the storefront cannot discover them by guessing ids.
     */
    public function show(Request $request, string $news): JsonResponse
    {
        $article = $this->visibleNews()
            ->where(function (Builder $query) use ($news): void {
                $query->where('slug', $news);
                if (ctype_digit($news)) {
                    $query->orWhere('id', (int) $news);
                }
            })
            // `name` is built from these two columns (User::getNameAttribute).
            ->with('author:id,first_name,last_name')
            ->first();

        if (! $article) {
            return $this->error('News article not found.', 404);
        }

        // Same category first; an uncategorised article just gets the latest.
        $related = $this->newestFirst(
            $this->visibleNews()
                ->whereKeyNot($article->id)
                ->when($article->category, fn ($query) => $query->where('category', $article->category)),
        )
            ->limit(self::RELATED_LIMIT)
            ->get();

        return $this->success(
            (new NewsDetailResource($article))->withRelated($related)->resolve($request),
        );
    }

    /** Published news whose publish date, if any, has arrived. */
    private function visibleNews(): Builder
    {
        return ContentItem::query()
            ->published()
            ->where('type', 'news')
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    private function newestFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id');
    }
}
