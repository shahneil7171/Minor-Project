<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    /**
     * Display the authenticated user's order history.
     */
    public function index(Request $request)
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(8);

        $orders->appends($request->query());

        return view('orders.index', compact('orders'));
    }

    /**
     * Show a single order with its tracking timeline.
     */
    public function show(Request $request, Order $order)
    {
        // A user may only view their own orders.
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load('items');

        return view('orders.show', compact('order'));
    }
}
