<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller order view: orders containing the seller's products.
 *
 * Sellers only ever see their own lines of an order — never another
 * seller's items. The status actions cover the seller part of the lifecycle:
 * confirmed -> processing -> ready_for_pickup ("packed, waiting for the
 * delivery partner"). Everything goes through OrderStatusService, so a
 * seller can never set picked_up / out_for_delivery / delivered, never skip
 * a step and never touch another seller's order.
 *
 * MULTI-SELLER NOTE: an order can contain lines from several sellers while
 * the LIFECYCLE status lives on the order itself. The first seller to act
 * therefore moves the shared order forward; per-line statuses are not part
 * of the current architecture (documented in the phase notes).
 */
class SellerOrdersController extends Controller
{
    public function __construct(
        private DeliveryService $deliveries,
        private OrderStatusService $statuses,
    ) {
    }

    /**
     * Orders that contain at least one line belonging to this seller.
     */
    public function index(Request $request)
    {
        $seller = $request->user();

        $status = $request->get('status', 'all');

        $query = Order::query()
            ->whereHas('items', fn ($q) => $q->where('seller_id', $seller->id))
            ->with(['items' => fn ($q) => $q->where('seller_id', $seller->id), 'user', 'delivery']);

        if ($status !== 'all' && in_array($status, Order::STATUSES, true)) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(8);
        $orders->appends($request->query());

        $statuses = Order::STATUSES;
        $statusLabels = Order::STATUS_LABELS;

        return view('seller.orders.index', compact('orders', 'status', 'statuses', 'statusLabels'));
    }

    /**
     * One order with only the seller's own lines.
     */
    public function show(Request $request, Order $order)
    {
        $seller = $request->user();

        // The seller must own at least one line of this order.
        if (! $order->items()->where('seller_id', $seller->id)->exists()) {
            abort(403, 'You can only view orders that contain your products.');
        }

        $order->load([
            'user',
            'delivery.deliveryPartner',
            'statusHistories.actor',
            'items' => fn ($q) => $q->where('seller_id', $seller->id),
        ]);

        return view('seller.orders.show', [
            'order'        => $order,
            'items'        => $order->items,
            'history'      => $order->statusHistories,
            'nextStatuses' => $order->allowedNextStatuses(),
        ]);
    }

    /**
     * Seller workflow actions: start processing, mark ready for pickup
     * ("packed"). Only lifecycle-valid, seller-owned transitions are accepted
     * — OrderStatusService re-checks ownership and the step server-side.
     */
    public function status(Request $request, Order $order)
    {
        $seller = $request->user();

        if (! $order->items()->where('seller_id', $seller->id)->exists()) {
            abort(403, 'You can only update orders that contain your products.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $data['status'];

        $note = $data['note'] ?? match ($newStatus) {
            'processing'       => 'Seller started processing the order.',
            'ready_for_pickup' => 'Product packed and ready for pickup.',
            default            => 'Status updated by seller.',
        };

        try {
            $this->statuses->transition($order, $newStatus, $seller, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        }

        // "Ready for pickup" also moves an assigned delivery forward and
        // alerts the delivery partner (DeliveryService notifies only once).
        if ($newStatus === 'ready_for_pickup' && $order->delivery) {
            $this->deliveries->markReadyForPickup($order->delivery);
        }

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }
}
