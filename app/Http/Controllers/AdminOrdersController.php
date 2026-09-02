<?php

namespace App\Http\Controllers;

use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class AdminOrdersController extends Controller
{
    public function __construct(
        private OrderWorkflowService $workflows,
        private DeliveryService $deliveries,
    ) {
    }
    /**
     * Display every order (admin only).
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');

        $query = Order::with(['user', 'items', 'delivery']);

        if ($status !== 'all' && in_array($status, Order::STATUSES)) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(15);
        $orders->appends($request->query());

        $statuses = Order::STATUSES;
        $statusLabels = Order::STATUS_LABELS;

        // Active delivery partners for the inline assignment forms
        // (loaded once instead of inside the row loop).
        $activePartners = \App\Models\User::query()
            ->where('account_type', 'delivery_partner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.orders.index', compact('orders', 'status', 'statuses', 'statusLabels', 'activePartners'));
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

        $oldStatus = $order->status;

        $order->update(['status' => $newStatus]);

        // Notify the customer about real status transitions only. Re-saving
        // the same status (no-op submit or refresh) never sends an email.
        $this->sendStatusTransitionEmail($order, $oldStatus, $newStatus);

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }

    /**
     * Email the customer when their order actually transitions to "shipped"
     * or "delivered".
     *
     * Duplicate protection lives in the transition check itself (old status
     * vs new status) — no session state and no extra database tables are
     * involved. A mail failure is logged and never blocks the status update.
     */
    private function sendStatusTransitionEmail(Order $order, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        if (! in_array($newStatus, ['shipped', 'delivered'], true)) {
            return;
        }

        // Order emails go only to the order's own customer address (the
        // registered email for account orders, the checkout email for guests).
        $recipient = $order->user?->email ?? $order->customer_email;

        if (! $recipient) {
            return;
        }

        try {
            if ($newStatus === 'shipped') {
                Mail::to($recipient)->send(new OrderShippedMail($order->load('items'), now()));
            } else {
                Mail::to($recipient)->send(new OrderDeliveredMail($order->load('items'), now()));
            }
        } catch (Throwable $e) {
            Log::error('Order status email failed for order ' . $order->order_number . ': ' . $e->getMessage());
        }
    }

    /**
     * Approve a pending order (admin only).
     *
     * The buyer is emailed, every seller with products in the order is
     * notified (each only with their own lines), and the order becomes
     * eligible for delivery-partner assignment.
     */
    public function approve(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        if (! $this->workflows->approve($order, $request->user())) {
            return back()->with(
                'error',
                'Order ' . $order->order_number . ' cannot be approved from its current status ('
                . $order->statusLabel() . ').'
            );
        }

        return back()->with('success', 'Order ' . $order->order_number . ' approved. Sellers and buyer have been notified.');
    }

    /**
     * Assign (or reassign) a delivery partner to an order (admin only).
     *
     * Only ACTIVE delivery partners are accepted; the assignment is stored
     * in the order_deliveries table (one row per order) and the partner is
     * notified.
     */
    public function assignDelivery(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        if (! $order->isApproved()) {
            return back()->with('error', 'Only approved orders can be assigned a delivery partner.');
        }

        $data = $request->validate([
            'delivery_partner_id' => ['required', 'integer'],
        ]);

        $this->deliveries->assign($order, (int) $data['delivery_partner_id'], $request->user());

        $partner = User::find($data['delivery_partner_id']);

        return back()->with('success', 'Delivery partner ' . ($partner?->name ?? '') . ' assigned to order ' . $order->order_number . '.');
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
