<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Notifications\StoreAlert;
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
 * the LIFECYCLE status lives on the order itself. Each seller now stamps
 * their OWN lines with seller_ready_at when they mark the parcel packed;
 * OrderStatusService refuses the ready_for_pickup step until every distinct
 * seller has stamped all of their lines, so a shared order sits in
 * "Processing — partially ready" until the last seller finishes. The first
 * seller to act still moves the shared order forward for the earlier
 * (confirmed -> processing) step.
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

        // MULTI-SELLER READINESS: has THIS seller already stamped all of
        // their own lines, and how many of the order's sellers are done?
        $sellerReady = $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->isSellerReady());

        return view('seller.orders.show', [
            'order'            => $order,
            'items'            => $order->items,
            'history'          => $order->statusHistories,
            'nextStatuses'     => $order->allowedNextStatuses(),
            'sellerReady'      => $sellerReady,
            'sellersReady'     => $order->readySellerCount(),
            'sellersTotal'     => count($order->distinctSellerIds()),
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

        // MULTI-SELLER READINESS: before asking the shared order to become
        // "Ready for Pickup", stamp THIS seller's own lines with the packing
        // timestamp. Only the lifecycle-eligible moment (processing ->
        // ready_for_pickup) stamps; a stray POST from any other status never
        // writes to the rows. The stamp persists even when the order cannot
        // advance yet — that is what makes the order "partially ready".
        if ($newStatus === 'ready_for_pickup' && $order->status === 'processing') {
            // update() returns the number of rows actually stamped, so a
            // double-click (already stamped) never re-notifies the admins.
            $newlyStamped = $order->items()
                ->where('seller_id', $seller->id)
                ->whereNull('seller_ready_at')
                ->update(['seller_ready_at' => now()]);

            // NOT every seller on this order has finished packing: leave the
            // shared order in Processing ("partially ready"). Tell the admin
            // how far along it is, and tell this seller the order follows
            // once the remaining sellers are done. OrderStatusService would
            // reject the transition, so it is not even attempted here.
            if ($newlyStamped > 0 && ! $order->allSellersReady()) {
                $readiness = $order->readySellerCount() . ' of ' . count($order->distinctSellerIds());

                $admins = User::where('account_type', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new StoreAlert(
                        'Seller Items Ready — Waiting for Other Sellers',
                        'Seller ' . $seller->name . ' has packed their items for order #' . $order->order_number
                            . ' (' . $readiness . ' sellers ready). The order becomes Ready for Pickup once every seller is done.',
                        route('admin.orders.show', $order),
                        [
                            'type'         => 'order_seller_items_ready',
                            'order_id'     => $order->id,
                            'order_number' => $order->order_number,
                            'seller_id'    => $seller->id,
                        ]
                    ));
                }

                return back()->with(
                    'success',
                    'Your items in order ' . $order->order_number . ' are marked ready for pickup ('
                        . $readiness . ' sellers ready). The order will move to Ready for Pickup once every seller has finished packing.'
                );
            }
        }

        try {
            $this->statuses->transition($order, $newStatus, $seller, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        }

        // "Ready for pickup" also moves an assigned delivery forward and
        // alerts the delivery partner (DeliveryService notifies only once).
        if ($newStatus === 'ready_for_pickup') {
            if ($order->delivery) {
                $this->deliveries->markReadyForPickup($order->delivery);
            }

            // Notify administrators so they can assign a delivery partner.
            $admins = User::where('account_type', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new StoreAlert(
                    'Order Ready for Pickup',
                    'Order #' . $order->order_number . ' is packed and ready for pickup. Please assign a delivery partner.',
                    route('admin.orders.show', $order),
                    [
                        'type'         => 'order_ready_for_pickup',
                        'order_id'     => $order->id,
                        'order_number' => $order->order_number,
                    ]
                ));
            }
        }

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->statusLabel() . '.'
        );
    }
}
