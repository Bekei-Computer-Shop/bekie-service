<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use App\Models\ContentItem;
use Illuminate\Support\Collection;

/**
 * One news article for its own page: the list fields plus the author and a few
 * related articles, passed in with `->withRelated($related)`.
 *
 * @mixin ContentItem
 */
class NewsDetailResource extends NewsResource
{
    /** @var Collection<int, ContentItem> */
    private Collection $related;

    /** @param Collection<int, ContentItem> $related */
    public function withRelated(Collection $related): static
    {
        $this->related = $related;

        return $this;
    }

    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'author' => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ] : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'related' => NewsResource::collection($this->related ?? collect())->resolve($request),
        ];
    }
}
