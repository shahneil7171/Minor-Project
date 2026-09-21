<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\ReturnPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrdersController extends Controller
{
    public function __construct(private OrderStatusService $statuses)
    {
    }

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
     * Show a single order with its tracking timeline and status history.
     */
    public function show(Request $request, Order $order)
    {
        // A user may only view their own orders; admins can open any
        // customer's order from the Customers page.
        $user = $request->user();
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        $order->load(['items', 'delivery.deliveryPartner', 'statusHistories.actor']);

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

        // Whether the Cancel button may be shown. The backend re-checks the
        // exact same rules (and the role) when the form is submitted.
        $canCancel = $this->statuses->canTransition($order, 'cancelled', $user)
            && (int) $order->user_id === (int) $user->id;

        return view('orders.show', [
            'order'        => $order,
            'returnInfo'   => $returnInfo,
            'history'      => $order->statusHistories,
            'canCancel'    => $canCancel,
        ]);
    }

    /**
     * Cancel an eligible order (buyer).
     *
     * Only pending / confirmed orders can be cancelled — enforced server-side
     * by OrderStatusService, never by hiding the button.
     */
    public function cancel(Request $request, Order $order)
    {
        $user = $request->user();

        // Ownership: a buyer may only cancel their own order (admins use the
        // admin panel endpoints).
        abort_unless((int) $order->user_id === (int) $user->id || $user->isAdmin(), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->statuses->transition(
                $order,
                'cancelled',
                $user,
                $data['note'] ?? 'Order cancelled by the buyer.',
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with(
                'error',
                collect($e->errors())->flatten()->first()
            );
        }

        return back()->with('success', 'Order ' . $order->order_number . ' has been cancelled.');
    }
}
