<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\AddWishlistItemRequest;
use App\Http\Requests\Api\Client\V1\StoreWishlistRequest;
use App\Http\Resources\Api\Client\V1\WishlistResource;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Wishlist::with('items.product', 'items.variant')
            ->where('user_id', $request->user()->id);

        return $this->success(WishlistResource::collection($query->orderBy('updated_at', 'desc')->paginate(15)));
    }

    public function store(StoreWishlistRequest $request)
    {
        $attributes = $request->validated();
        unset($attributes['user_id'], $attributes['session_id']);

        $wishlist = Wishlist::create([
            ...$attributes,
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new WishlistResource($wishlist));
    }

    public function show(Wishlist $wishlist)
    {
        return $this->success(new WishlistResource($wishlist->load('items.product', 'items.variant')));
    }

    public function destroy(Wishlist $wishlist)
    {
        $wishlist->delete();

        return $this->noContent();
    }

    public function addItem(AddWishlistItemRequest $request, Wishlist $wishlist)
    {
        $item = $wishlist->items()->create([
            'product_id' => $request->product_id,
            'product_variant_id' => $request->product_variant_id,
            'quantity' => $request->input('quantity', 1),
            'metadata' => $request->metadata,
        ]);

        return $this->created($item);
    }

    public function removeItem(Wishlist $wishlist, $item)
    {
        $wishlist->items()->whereKey($item)->delete();

        return $this->noContent();
    }
}
