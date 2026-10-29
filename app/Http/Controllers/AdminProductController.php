<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->where(function ($q) use ($request) {
                $q->where('seller_id', $request->user()->id)
                  ->orWhere(function ($q2) {
                      $q2->whereNull('seller_id')->where('owner_type', 'store');
                  });
            })
            ->orderByDesc('id')
            ->get();

        return view('seller.products', ['products' => $products]);
    }
}
