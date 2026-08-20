<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrdersController extends Controller
{
    /**
     * Display every order (admin only).
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');

        $query = Order::with(['user', 'items']);

        if ($status !== 'all' && in_array($status, Order::STATUSES)) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(15);
        $orders->appends($request->query());

        return view('admin.orders.index', compact('orders', 'status'));
    }

    /**
     * Update the status of an order (admin only).
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);

        $order->update(['status' => $data['status']]);

        return back()->with('success', 'Order ' . $order->order_number . ' marked as ' . $data['status'] . '.');
    }

    /**
     * Only admins can manage orders.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->account_type === 'admin', 403);
    }
}
