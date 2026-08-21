<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $statuses = Order::STATUSES;
        $statusLabels = Order::STATUS_LABELS;

        return view('admin.orders.index', compact('orders', 'status', 'statuses', 'statusLabels'));
    }

    /**
     * Update the status of an order (admin only).
     *
     * The requested status is validated against the allowed list and then the
     * transition is verified so invalid/backwards moves are rejected server-side.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $newStatus = $data['status'];

        if (! $order->canTransitionTo($newStatus)) {
            return back()->with(
                'error',
                'Cannot change order ' . $order->order_number . ' from '
                . $order->statusLabel() . ' to ' . (Order::STATUS_LABELS[$newStatus] ?? $newStatus) . '.'
            );
        }

        $order->update(['status' => $newStatus]);

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }

    /**
     * Render a printable invoice for an order (admin only).
     *
     * Reached from Sales > Customers > (customer) > Order history.
     */
    public function invoice(Order $order)
    {
        $this->authorizeAdmin();

        $order->load(['user', 'items']);

        return view('admin.orders.invoice', compact('order'));
    }

    /**
     * Only admins can manage orders.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->account_type === 'admin', 403);
    }
}
