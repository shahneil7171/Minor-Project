<?php

namespace App\Http\Controllers;

use App\Models\OrderDelivery;
use App\Models\ReturnRequest;
use App\Services\DeliveryService;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Delivery Partner area: dashboard, assigned deliveries and the controlled
 * status actions (picked up / out for delivery / delivered), plus the return
 * pickups assigned by the admin.
 *
 * Route-level middleware ("auth" + "delivery.partner") plus the server-side
 * ownership checks inside DeliveryController/DeliveryService/ReturnService
 * guarantee a partner can only ever see and update their own assignments.
 */
class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveries,
        private ReturnService $returns,
    ) {
    }

    /**
     * Delivery dashboard with live counters.
     */
    public function dashboard(Request $request)
    {
        $partner = $request->user();

        $base = OrderDelivery::query()->where('delivery_partner_id', $partner->id);

        $stats = [
            'total'            => (clone $base)->count(),
            'pending'          => (clone $base)->where('status', 'assigned')->count(),
            'ready_for_pickup' => (clone $base)->where('status', 'ready_for_pickup')->count(),
            'picked_up'        => (clone $base)->where('status', 'picked_up')->count(),
            'out_for_delivery' => (clone $base)->where('status', 'out_for_delivery')->count(),
            'delivered'        => (clone $base)->where('status', 'delivered')->count(),
            'failed'           => (clone $base)->where('status', 'failed')->count(),
        ];

        // Active work queue: everything not yet delivered/failed.
        $deliveries = (clone $base)
            ->whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up', 'out_for_delivery'])
            ->with(['order.user', 'order.items.seller', 'order.delivery'])
            ->get()
            ->sortBy([
                // Priority: ready_for_pickup first, then assigned, then
                // picked_up, then out_for_delivery (portable across SQLite
                // and MySQL — FIELD() is MySQL only).
                ['status', fn ($a, $b) => [
                    'ready_for_pickup' => 0,
                    'assigned'         => 1,
                    'picked_up'        => 2,
                    'out_for_delivery' => 3,
                ][$a] <=> [
                    'ready_for_pickup' => 0,
                    'assigned'         => 1,
                    'picked_up'        => 2,
                    'out_for_delivery' => 3,
                ][$b]],
                ['assigned_at', 'asc'],
            ])
            ->values();

        return view('delivery.dashboard', compact('stats', 'deliveries'));
    }

    /**
     * My Deliveries (active) / Delivery History (?status= filter).
     */
    public function index(Request $request)
    {
        $partner = $request->user();
        $status = $request->get('status', 'active');

        $query = OrderDelivery::query()
            ->where('delivery_partner_id', $partner->id)
            ->with(['order.user', 'order.items']);

        if ($status === 'active') {
            $query->whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up', 'out_for_delivery']);
        } elseif ($status === 'all') {
            // Full history including delivered + failed.
        } elseif (in_array($status, OrderDelivery::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $status = 'active';
            $query->whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up', 'out_for_delivery']);
        }

        $deliveries = $query->latest('assigned_at')->paginate(10);
        $deliveries->appends($request->query());

        $statuses = OrderDelivery::STATUSES;
        $statusLabels = OrderDelivery::STATUS_LABELS;

        return view('delivery.deliveries.index', compact('deliveries', 'status', 'statuses', 'statusLabels'));
    }

    /**
     * One assigned delivery: customer, order and delivery information.
     */
    public function show(Request $request, OrderDelivery $delivery)
    {
        $this->authorizePartner($request, $delivery);

        $delivery->load(['order.user', 'order.items.seller', 'deliveryPartner', 'assignedBy']);

        return view('delivery.deliveries.show', ['delivery' => $delivery, 'order' => $delivery->order]);
    }

    /**
     * Mark an assigned delivery as picked up.
     */
    public function pickup(Request $request, OrderDelivery $delivery)
    {
        $this->authorizePartner($request, $delivery);

        $this->deliveries->transition($delivery, 'picked_up', $request->user(), $request->input('notes'));

        return back()->with('success', 'Order #' . $delivery->order->order_number . ' marked as picked up.');
    }

    /**
     * Mark a picked-up delivery as out for delivery (order becomes shipped).
     */
    public function outForDelivery(Request $request, OrderDelivery $delivery)
    {
        $this->authorizePartner($request, $delivery);

        $this->deliveries->transition($delivery, 'out_for_delivery', $request->user(), $request->input('notes'));

        return back()->with('success', 'Order #' . $delivery->order->order_number . ' marked as out for delivery.');
    }

    /**
     * Mark an out-for-delivery delivery as delivered (order becomes delivered).
     */
    public function delivered(Request $request, OrderDelivery $delivery)
    {
        $this->authorizePartner($request, $delivery);

        $this->deliveries->transition($delivery, 'delivered', $request->user(), $request->input('notes'));

        return back()->with('success', 'Order #' . $delivery->order->order_number . ' marked as delivered.');
    }

    /**
     * Server-side ownership check: a partner may only open their own delivery.
     */
    private function authorizePartner(Request $request, OrderDelivery $delivery): void
    {
        if ((int) $delivery->delivery_partner_id !== (int) $request->user()->id) {
            abort(403, 'You can only view deliveries assigned to you.');
        }
    }

    // ------------------------------------------------------------------
    // Return pickups (Return #RET-xxxx: customer -> partner -> seller)
    // ------------------------------------------------------------------

    /**
     * Return pickups assigned to this partner.
     */
    public function pickupsIndex(Request $request)
    {
        $partner = $request->user();
        $status = $request->get('status', 'active');

        $query = ReturnRequest::query()
            ->where('delivery_partner_id', $partner->id)
            ->with(['order', 'orderItem']);

        if ($status === 'active') {
            $query->whereIn('status', ['pickup_scheduled', 'picked_up']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $pickups = $query->latest('pickup_scheduled_at')->paginate(10);
        $pickups->appends($request->query());

        return view('delivery.pickups.index', [
            'pickups'      => $pickups,
            'status'       => $status,
            'statuses'     => ReturnRequest::STATUSES,
            'statusLabels' => ReturnRequest::STATUS_LABELS,
        ]);
    }

    /**
     * One return pickup: customer, address, product and pickup instructions.
     * Only the assigned partner may open it (403 otherwise).
     */
    public function pickupShow(Request $request, ReturnRequest $pickup)
    {
        $this->authorizePickup($request, $pickup);

        $pickup->load(['order.items', 'orderItem', 'customer', 'seller', 'assignedBy']);

        return view('delivery.pickups.show', ['pickup' => $pickup]);
    }

    /**
     * Mark the parcel as collected from the customer (return -> received).
     */
    public function collect(Request $request, ReturnRequest $pickup)
    {
        $this->authorizePickup($request, $pickup);

        try {
            $this->returns->markReceived($pickup, $request->user(), $request->input('notes'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with(
            'success',
            'Return ' . $pickup->return_number . ' for Order #' . $pickup->order_number . ' marked as received.'
        );
    }

    /**
     * Server-side ownership check for return pickups.
     */
    private function authorizePickup(Request $request, ReturnRequest $pickup): void
    {
        if ((int) $pickup->delivery_partner_id !== (int) $request->user()->id) {
            abort(403, 'You can only view return pickups assigned to you.');
        }
    }
}

