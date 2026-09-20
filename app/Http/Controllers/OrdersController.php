<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\ReturnPolicy;
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

        $order->load(['items', 'delivery.deliveryPartner']);

        // Per-line return eligibility for the "Return Product" button. The
        // exact same rules are re-verified server-side when a return is
        // actually submitted (the UI is never trusted).
        $returnInfo = [];
        foreach ($order->items as $item) {
            $item->setRelation('order', $order); // avoid per-item lazy loads

            $check = ReturnPolicy::check($item, 1);

            $returnInfo[$item->id] = [
                'eligible'   => $check['eligible'],
                'reason'     => $check['reason'],
                'returnable' => $item->returnableQuantity(),
            ];
        }

        return view('orders.show', compact('order', 'returnInfo'));
    }
}
