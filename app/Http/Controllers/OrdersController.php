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
        $status = $request->get('status', 'all');
        $statuses = Order::STATUSES;
        $statusLabels = Order::STATUS_LABELS;

        $query = Order::with('items')
            ->where('user_id', $request->user()->id);

        if ($status !== 'all' && in_array($status, Order::STATUSES)) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(8);
        $orders->appends($request->query());

        return view('orders.index', compact('orders', 'status', 'statuses', 'statusLabels'));
    }

    /**
     * Show a single order with its tracking timeline.
     */
    public function show(Request $request, Order $order)
    {
        // A user may only view their own orders; admins can open any
        // customer's order from the Customers page.
        $user = $request->user();
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        $order->load('items');

        return view('orders.show', compact('order'));
    }
}
