<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\Review\IndexReviewsRequest;
use App\Http\Requests\Api\Client\V1\Review\StoreReviewRequest;
use App\Http\Requests\Api\Client\V1\Review\UpdateReviewRequest;
use App\Http\Resources\Api\Client\V1\ReviewResource;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewController extends BaseApiController
{
    public function index(IndexReviewsRequest $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        $query = Review::where('user_id', $request->user()->id)
            ->with(['product']);

        $total = (clone $query)->count();
        $items = $query->orderBy($sort, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success([
            'items' => ReviewResource::collection($items)->resolve($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request)
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $existingReview = Review::where('user_id', $userId)
            ->where('product_id', $data['product_id'])
            ->exists();

        if ($existingReview) {
            return $this->error('You have already reviewed this product.', 422, [
                'product_id' => ['You can only submit one review per product.'],
            ]);
        }

        $review = Review::create([
            'user_id' => $userId,
            'product_id' => $data['product_id'],
            'order_id' => $data['order_id'] ?? null,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'status' => 'pending',
        ]);

        $review->load('product');

        return $this->created(new ReviewResource($review), 'Review submitted successfully.');
    }

    public function show(Review $review)
    {
        if ($review->user_id !== auth()->id()) {
            return $this->error('You are not authorized to view this review.', 403);
        }

        $review->load('product');

        return $this->success(new ReviewResource($review));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id) {
            return $this->error('You are not authorized to update this review.', 403);
        }

        $data = $request->validated();
        $review->update($data);
        $review->load('product');

        return $this->success(new ReviewResource($review), 'Review updated successfully.');
    }

    public function destroy(Review $review)
    {
        if ($review->user_id !== auth()->id()) {
            return $this->error('You are not authorized to delete this review.', 403);
        }

        $review->delete();

        return $this->noContent();
    }
}
