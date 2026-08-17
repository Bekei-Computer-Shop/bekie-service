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
        $query = Wishlist::with('items.product', 'items.variant');

        $query->where('user_id', $request->user()->id);

        return $this->success(WishlistResource::collection($query->orderBy('updated_at', 'desc')->paginate(15)));
    }

    public function store(StoreWishlistRequest $request)
    {
        $wishlist = Wishlist::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
            'session_id' => null,
        ]));

        return $this->created(new WishlistResource($wishlist));
    }

    public function show(Request $request, Wishlist $wishlist)
    {
        $this->ensureWishlistAccess($request, $wishlist);

        return $this->success(new WishlistResource($wishlist->load('items.product', 'items.variant')));
    }

    public function destroy(Request $request, Wishlist $wishlist)
    {
        $this->ensureWishlistAccess($request, $wishlist);

        $wishlist->delete();

        return $this->noContent();
    }

    public function addItem(AddWishlistItemRequest $request, Wishlist $wishlist)
    {
        $this->ensureWishlistAccess($request, $wishlist);

        $item = $wishlist->items()->create([
            'product_id' => $request->product_id,
            'product_variant_id' => $request->product_variant_id,
            'quantity' => $request->input('quantity', 1),
            'metadata' => $request->metadata,
        ]);

        return $this->created($item);
    }

    public function removeItem(Request $request, Wishlist $wishlist, $item)
    {
        $this->ensureWishlistAccess($request, $wishlist);

        $wishlist->items()->whereKey($item)->delete();

        return $this->noContent();
    }

    private function ensureWishlistAccess(Request $request, Wishlist $wishlist): void
    {
        abort_unless((int) $wishlist->user_id === (int) $request->user()->id, 403, 'You do not have access to this wishlist.');
    }
}
