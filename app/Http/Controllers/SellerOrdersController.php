<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seller order view: orders containing the seller's products.
 *
 * Sellers only ever see their own lines of an order — never another
 * seller's items. The status actions cover the seller part of the delivery
 * workflow: approved -> processing -> packed ("ready for pickup"), which
 * moves an assigned delivery to ready_for_pickup and notifies the partner.
 */
class SellerOrdersController extends Controller
{
    public function __construct(private DeliveryService $deliveries)
    {
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
            'items' => fn ($q) => $q->where('seller_id', $seller->id),
        ]);

        return view('seller.orders.show', ['order' => $order, 'items' => $order->items]);
    }

    /**
     * Seller workflow actions: start processing, mark packed (ready for
     * pickup). Only forward transitions on orders containing their products.
     */
    public function status(Request $request, Order $order)
    {
        $seller = $request->user();

        if (! $order->items()->where('seller_id', $seller->id)->exists()) {
            abort(403, 'You can only update orders that contain your products.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'packed'])],
        ]);

        if (! $order->canTransitionTo($data['status'])) {
            return back()->with(
                'error',
                'Cannot change order ' . $order->order_number . ' from '
                . $order->statusLabel() . ' to ' . Order::STATUS_LABELS[$data['status']] . '.'
            );
        }

        $order->update(['status' => $data['status']]);

        // "Packed" = ready for pickup: move an assigned delivery forward and
        // alert the delivery partner (DeliveryService handles the once-only
        // notification).
        if ($data['status'] === 'packed' && $order->delivery) {
            $this->deliveries->markReadyForPickup($order->delivery);
        }

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }
}
