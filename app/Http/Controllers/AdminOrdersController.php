<?php

namespace App\Http\Controllers;

use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderStatusService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminOrdersController extends Controller
{
    public function __construct(
        private OrderWorkflowService $workflows,
        private DeliveryService $deliveries,
        private OrderStatusService $statuses,
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
     * Admin order details: current status, full status timeline, the audit
     * trail and ONLY the actions that are valid for the current status.
     *
     * The same rules are re-checked server-side by OrderStatusService, so
     * hiding a button is never the actual protection.
     */
    public function show(Order $order)
    {
        $this->authorizeAdmin();

        $order->load(['user', 'items.seller', 'delivery.deliveryPartner', 'statusHistories.actor']);

        $activePartners = User::query()
            ->where('account_type', 'delivery_partner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.orders.show', [
            'order'          => $order,
            'activePartners' => $activePartners,
            'nextStatuses'   => $order->allowedNextStatuses(),
            'history'        => $order->statusHistories,
        ]);
    }

    /**
     * Update the status of an order (admin only).
     *
     * The requested status is validated against the canonical list and then
     * the transition is verified server-side, so invalid/backwards moves and
     * jumps are rejected. Delivery-owned statuses are routed through the
     * delivery layer so the order and its delivery row can never drift apart.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $data['status'];
        $note = $data['note'] ?? null;

        if (in_array($newStatus, Order::DELIVERY_OWNED_STATUSES, true) && $order->delivery) {
            try {
                $this->deliveries->transition($order->delivery, $newStatus, $request->user(), $note);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->with('error', $this->firstError($e));
            }

            return back()->with(
                'success',
                'Order ' . $order->order_number . ' marked as ' . $order->refresh()->statusLabel() . '.'
            );
        }

        $oldStatus = $order->status;

        try {
            $this->statuses->transition($order, $newStatus, $request->user(), $note ?: 'Status updated by admin.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', $this->firstError($e));
        }

        // Legacy orders can reach a delivery milestone without a delivery row:
        // keep the existing buyer mails on that path.
        $this->sendStatusTransitionEmail($order, $oldStatus, $newStatus);

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }

    /**
     * Cancel an order (admin only).
     *
     * Only lifecycle-valid cancellations (pending / confirmed) are accepted —
     * the centralized service enforces this server-side.
     */
    public function cancel(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->statuses->transition(
                $order,
                'cancelled',
                $request->user(),
                $data['note'] ?? 'Order cancelled by admin.',
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', $this->firstError($e));
        }

        return back()->with('success', 'Order ' . $order->order_number . ' cancelled.');
    }

    /**
     * Human message for the first validation failure.
     */
    private function firstError(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            if (! empty($messages)) {
                return (string) $messages[0];
            }
        }

        return 'The order status could not be updated.';
    }

    /**
     * Email the customer when their order actually reaches "out for delivery"
     * or "delivered" without going through the delivery layer.
     *
     * Duplicate protection lives in the transition check itself (old status
     * vs new status). A mail failure is logged and never blocks the update.
     */
    private function sendStatusTransitionEmail(Order $order, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        if (! in_array($newStatus, ['out_for_delivery', 'delivered'], true)) {
            return;
        }

        // Order emails go only to the order's own customer address (the
        // registered email for account orders, the checkout email for guests).
        $recipient = $order->user?->email ?? $order->customer_email;

        if (! $recipient) {
            return;
        }

        try {
            if ($newStatus === 'out_for_delivery') {
                Mail::to($recipient)->send(new OrderShippedMail($order->load('items'), now()));
            } else {
                Mail::to($recipient)->send(new OrderDeliveredMail($order->load('items'), now()));
            }
        } catch (Throwable $e) {
            Log::error('Order status email failed for order ' . $order->order_number . ': ' . $e->getMessage());
        }
    }

    /**
     * Confirm a pending order (admin only).
     *
     * The buyer is emailed, every seller with products in the order is
     * notified (each only with their own lines), and the order becomes
     * eligible for the seller processing -> ready-for-pickup steps.
     */
    public function approve(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        if (! $this->workflows->approve($order, $request->user())) {
            return back()->with(
                'error',
                'Order ' . $order->order_number . ' cannot be confirmed from its current status ('
                . $order->statusLabel() . ').'
            );
        }

        return back()->with('success', 'Order ' . $order->order_number . ' confirmed. Sellers and buyer have been notified.');
    }

    /**
     * Assign (or reassign) a delivery partner to an order (admin only).
     *
     * The order must already be READY FOR PICKUP: assignment is the
     * ready_for_pickup -> assigned step of the lifecycle. Only ACTIVE delivery
     * partners are accepted; the assignment is stored in the order_deliveries
     * table (one row per order), the order status becomes "assigned" and the
     * partner is notified. Assignment never marks anything as delivered.
     */
    public function assignDelivery(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        if ($order->status === 'pending') {
            return back()->with('error', 'Order ' . $order->order_number . ' cannot be assigned a delivery partner while it is still ' . $order->statusLabel() . '.');
        }

        $existingDelivery = $order->delivery;

        // A first assignment requires ready_for_pickup. Reassigning an
        // existing delivery is always allowed (operational exception).
        if (! $existingDelivery && $order->status !== 'ready_for_pickup') {
            return back()->with(
                'error',
                'Order ' . $order->order_number . ' must be marked Ready for Pickup before a delivery partner can be assigned (current status: '
                . $order->statusLabel() . ').'
            );
        }

        $data = $request->validate([
            'delivery_partner_id' => ['required', 'integer'],
        ]);

        $this->deliveries->assign($order, (int) $data['delivery_partner_id'], $request->user());

        $partner = User::find($data['delivery_partner_id']);

        // Assignments that did not change anything (same partner re-saved)
        // never re-send the assignment email/notification.
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
