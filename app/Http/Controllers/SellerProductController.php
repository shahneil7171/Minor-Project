<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seller "My Products" management list.
 *
 * The database query itself is scoped to the authenticated seller, so
 * products owned by other sellers can never appear here — this is not a
 * frontend filter.
 */
class SellerProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->where('seller_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('seller.products', ['products' => $products]);
    }
}
