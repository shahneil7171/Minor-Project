<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\ProductCatalogService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        private readonly ProductCatalogService $catalog
    ) {
    }

    /**
     * Display the authenticated user's wishlist.
     */
    public function index(Request $request)
    {
        $products = $this->catalog->all();

        $saved = WishlistItem::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        // Resolve full product data for every saved slug. Products that were
        // removed from the catalog are filtered out silently.
        $items = [];
        foreach ($saved as $item) {
            if (isset($products[$item->product_slug])) {
                $items[$item->product_slug] = $products[$item->product_slug];
            }
        }

        $wishlistCount = count($items);

        return view('wishlist', compact('items', 'wishlistCount'));
    }

    /**
     * Toggle a product in the wishlist (add when missing, remove when saved).
     */
    public function toggle(Request $request, string $slug)
    {
        if (! $this->catalog->exists($slug)) {
            abort(404);
        }

        $exists = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_slug', $slug)
            ->exists();

        if ($exists) {
            WishlistItem::where('user_id', $request->user()->id)
                ->where('product_slug', $slug)
                ->delete();

            return back()->with('status', 'Removed from wishlist.');
        }

        WishlistItem::create([
            'user_id' => $request->user()->id,
            'product_slug' => $slug,
        ]);

        return back()->with('status', 'Added to wishlist.');
    }

    /**
     * Remove a single product from the wishlist.
     */
    public function remove(Request $request, string $slug)
    {
        WishlistItem::where('user_id', $request->user()->id)
            ->where('product_slug', $slug)
            ->delete();

        return back()->with('status', 'Removed from wishlist.');
    }

    /**
     * Move a wishlist product into the cart (and out of the wishlist).
     */
    public function toCart(Request $request, string $slug)
    {
        if ($request->user()->account_type === 'seller') {
            return back()->with('error', 'Sellers cannot add items to cart.');
        }

        $product = $this->catalog->find($slug);
        if (! $product) {
            abort(404);
        }

        WishlistItem::where('user_id', $request->user()->id)
            ->where('product_slug', $slug)
            ->delete();

        $cart = session()->get('cart', []);

        if (isset($cart[$slug])) {
            $cart[$slug]['quantity']++;
        } else {
            $price = $this->effectivePrice($product);

            $cart[$slug] = [
                'title' => $product['title'],
                'price' => $price,
                'quantity' => 1,
            ];
        }

        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Product moved to cart!');
    }

    /**
     * Effective sale price for a product (special_price wins when lower).
     */
    private function effectivePrice(array $product): float
    {
        $base = (float) ($product['price'] ?? 0);
        $special = isset($product['special_price']) && $product['special_price'] !== ''
            ? (float) $product['special_price']
            : 0;

        return ($special > 0 && $special < $base) ? $special : $base;
    }
}
