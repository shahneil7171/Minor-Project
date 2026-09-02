<?php

namespace App\Http\Controllers;

use App\Models\OrderDelivery;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

/**
 * Delivery Partner area: dashboard, assigned deliveries and the controlled
 * status actions (picked up / out for delivery / delivered).
 *
 * Route-level middleware ("auth" + "delivery.partner") plus the server-side
 * ownership check inside DeliveryService::transition() guarantee a partner
 * can only ever see and update their own assignments.
 */
class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveries)
    {
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
            ->with(['order.user', 'order.items', 'order.delivery'])
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

        $delivery->load(['order.user', 'order.items', 'deliveryPartner', 'assignedBy']);

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
}
