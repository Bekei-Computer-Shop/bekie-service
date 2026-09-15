<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Review\IndexReviewsRequest;
use App\Http\Requests\Api\Admin\V1\Review\UpdateReviewRequest;
use App\Http\Resources\Api\Admin\V1\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewController extends BaseAdminController
{
    public function index(IndexReviewsRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        $query = Review::query()
            ->with(['user', 'product', 'order'])
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->input('q').'%';
                $q->where(function ($query) use ($like): void {
                    $query->where('comment', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', (int) $request->input('rating')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->input('product_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('user_id', (int) $request->input('customer_id')));

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
            'filters' => [
                'status' => $request->input('status'),
                'rating' => $request->input('rating'),
                'product_id' => $request->input('product_id'),
                'customer_id' => $request->input('customer_id'),
                'q' => $request->input('q'),
            ],
        ]);
    }

    public function show(Review $review): JsonResponse
    {
        $review->load(['user', 'product', 'order']);

        return $this->success(new ReviewResource($review));
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        $data = $request->validated();
        $review->update($data);
        $review->load(['user', 'product', 'order']);

        return $this->success(new ReviewResource($review), 'Review updated successfully.');
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->forceDelete();

        return $this->success(null, 'Review deleted successfully.');
    }
}
