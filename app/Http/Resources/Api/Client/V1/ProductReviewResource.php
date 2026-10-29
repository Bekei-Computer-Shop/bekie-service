<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Client\V1;

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An approved review as anyone browsing the product sees it. The reviewer is
 * shown by first name and last initial only — no email, id or order.
 */
class ProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'images' => $review->images ?? [],
            'is_verified_purchase' => (bool) $review->is_verified_purchase,
            'is_featured' => (bool) $review->is_featured,
            'helpful_count' => (int) $review->helpful_count,
            'customer' => [
                'name' => $this->displayName($review->user),
                'avatar' => $this->avatarUrl($review->user?->avatar),
            ],
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    /** "Sokha Dara" -> "Sokha D." */
    private function displayName(?User $user): string
    {
        $first = trim((string) $user?->first_name);
        $last = trim((string) $user?->last_name);

        if ($first === '') {
            return 'Customer';
        }

        return $last === '' ? $first : $first.' '.Str::upper(Str::substr($last, 0, 1)).'.';
    }

    /** Same resolution as UserProfileResource: stored paths become public URLs. */
    private function avatarUrl(?string $avatar): ?string
    {
        if ($avatar === null || $avatar === '' || preg_match('#^(https?://|data:|//)#i', $avatar) === 1) {
            return $avatar ?: null;
        }

        return Storage::disk('public')->url($avatar);
    }
}
