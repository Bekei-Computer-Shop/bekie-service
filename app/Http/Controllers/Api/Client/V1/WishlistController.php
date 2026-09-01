<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\AddWishlistItemRequest;
use App\Http\Requests\Api\Client\V1\StoreWishlistRequest;
use App\Http\Resources\Api\Client\V1\WishlistResource;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * Wishlist Management
 *
 * Manage customer wishlists for saving products for later purchase.
 */
class WishlistController extends BaseApiController
{
    /**
     * Get authenticated user's wishlist
     *
     * Returns the authenticated user's single wishlist with all items.
     * Automatically creates the wishlist if it doesn't exist.
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "user_id": 5,
     *     "name": "My Wishlist",
     *     "description": null,
     *     "is_public": false,
     *     "is_active": true,
     *     "items": [...]
     *   }
     * }
     */
    public function index(Request $request)
    {
        $wishlist = Wishlist::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => 'My Wishlist',
                'description' => null,
                'is_public' => false,
                'is_active' => true,
                'session_id' => null,
            ]
        );

        return $this->success(new WishlistResource($wishlist->load('items.product', 'items.variant')));
    }

    /**
     * Update authenticated user's wishlist
     *
     * Updates the name, description, and visibility settings of the user's wishlist.
     *
     * @bodyParam name string The name of the wishlist. Example: "Birthday Ideas"
     * @bodyParam description string The description of the wishlist. Example: "Things I want for my birthday"
     * @bodyParam is_public boolean Whether the wishlist is publicly visible. Example: false
     * @bodyParam is_active boolean Whether the wishlist is active. Example: true
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "user_id": 5,
     *     "name": "Birthday Ideas",
     *     "description": "Things I want for my birthday",
     *     "is_public": false,
     *     "is_active": true,
     *     "items": []
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The name field is required.",
     *   "errors": {"name": ["The name field is required."]}
     * }
     */
    public function store(StoreWishlistRequest $request)
    {
        $wishlist = Wishlist::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => 'My Wishlist',
                'description' => null,
                'is_public' => false,
                'is_active' => true,
                'session_id' => null,
            ]
        );

        $wishlist->update($request->validated());

        return $this->success(new WishlistResource($wishlist->fresh()->load('items.product', 'items.variant')));
    }

    /**
     * Clear wishlist (kept for compatibility, doesn't actually delete)
     *
     * Removes all items from the wishlist without deleting the wishlist itself.
     *
     * @response 204 Wishlist cleared successfully
     */
    public function destroy(Request $request)
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)->firstOrFail();

        $wishlist->items()->delete();

        return $this->noContent();
    }

    /**
     * Add product to authenticated user's wishlist
     *
     * Adds a product (with optional variant) to the user's wishlist.
     * Prevents duplicate items from being added.
     *
     * @bodyParam product_id integer required The product ID. Example: 1
     * @bodyParam product_variant_id integer The product variant ID (optional). Example: 1
     * @bodyParam quantity integer The quantity (default: 1). Example: 1
     * @bodyParam metadata object Additional metadata as JSON. Example: {}
     *
     * @response 201 {
     *   "status": "success",
     *   "data": {
     *     "id": 1,
     *     "wishlist_id": 1,
     *     "product_id": 5,
     *     "product_variant_id": null,
     *     "quantity": 1
     *   }
     * }
     * @response 422 {
     *   "status": "error",
     *   "message": "The product id field is required.",
     *   "errors": {"product_id": ["The product id field is required."]}
     * }
     */
    public function addItem(AddWishlistItemRequest $request)
    {
        $wishlist = Wishlist::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => 'My Wishlist',
                'description' => null,
                'is_public' => false,
                'is_active' => true,
                'session_id' => null,
            ]
        );

        $item = $wishlist->items()->updateOrCreate(
            [
                'product_id' => $request->product_id,
                'product_variant_id' => $request->product_variant_id,
            ],
            [
                'quantity' => $request->input('quantity', 1),
                'metadata' => $request->metadata,
            ]
        );

        return $this->created($item);
    }

    /**
     * Remove product from authenticated user's wishlist
     *
     * Removes a product from the user's wishlist.
     *
     * @response 204 Item removed successfully
     * @response 404 Item not found
     */
    public function removeItem(Request $request, $item)
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)->firstOrFail();

        $wishlist->items()->whereKey($item)->delete();

        return $this->noContent();
    }

    /**
     * Check if product is in authenticated user's wishlist
     *
     * Checks whether a specific product (with optional variant) is in the user's wishlist.
     *
     * @queryParam product_id integer required The product ID. Example: 1
     * @queryParam product_variant_id integer The product variant ID (optional). Example: 1
     *
     * @response 200 {
     *   "status": "success",
     *   "data": {
     *     "exists": true,
     *     "item": {
     *       "id": 1,
     *       "wishlist_id": 1,
     *       "product_id": 5,
     *       "product_variant_id": null
     *     }
     *   }
     * }
     */
    public function checkProduct(Request $request)
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)->first();

        if (! $wishlist) {
            return $this->success([
                'exists' => false,
                'item' => null,
            ]);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $query = $wishlist->items()
            ->where('product_id', $validated['product_id']);

        if ($validated['product_variant_id'] ?? null) {
            $query->where('product_variant_id', $validated['product_variant_id']);
        } else {
            $query->whereNull('product_variant_id');
        }

        $item = $query->first();

        return $this->success([
            'exists' => (bool) $item,
            'item' => $item,
        ]);
    }
}
