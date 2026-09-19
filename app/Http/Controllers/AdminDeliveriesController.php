<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin > Deliveries: every assignment, unassigned approved orders,
 * assignment/reassignment and failure handling.
 */
class AdminDeliveriesController extends Controller
{
    public function __construct(private DeliveryService $deliveries)
    {
    }

    /**
     * All deliveries with filters + the unassigned approved orders queue.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');
        $partnerId = $request->get('partner');
        $search = trim((string) $request->get('search', ''));

        $query = OrderDelivery::query()->with(['order.user', 'order.items', 'deliveryPartner']);

        if ($status !== 'all' && in_array($status, OrderDelivery::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($partnerId) {
            $query->where('delivery_partner_id', (int) $partnerId);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', $like)
                    ->orWhere('shipping_name', 'like', $like)
                    ->orWhere('shipping_phone', 'like', $like)
                    ->orWhere('shipping_city', 'like', $like))
                    ->orWhereHas('deliveryPartner', fn ($pq) => $pq->where('name', 'like', $like));
            });
        }

        $deliveries = $query->latest('assigned_at')->paginate(15);
        $deliveries->appends($request->query());

        // Approved (or further along) orders that still have no delivery row.
        $unassigned = Order::query()
            ->whereIn('status', ['approved', 'processing', 'packed'])
            ->whereDoesntHave('delivery')
            ->with(['user', 'items'])
            ->latest('approved_at')
            ->paginate(10, ['*'], 'unassigned_page');

        $partners = User::query()
            ->where('account_type', 'delivery_partner')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $stats = [
            'pending_approval' => Order::where('status', 'pending')->count(),
            'unassigned'       => Order::whereIn('status', ['approved', 'processing', 'packed'])->whereDoesntHave('delivery')->count(),
            'assigned'         => OrderDelivery::whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up'])->count(),
            'out_for_delivery' => OrderDelivery::where('status', 'out_for_delivery')->count(),
            'delivered'        => OrderDelivery::where('status', 'delivered')->count(),
            'failed'           => OrderDelivery::where('status', 'failed')->count(),
        ];

        $statuses = OrderDelivery::STATUSES;
        $statusLabels = OrderDelivery::STATUS_LABELS;

        return view('admin.deliveries.index', compact(
            'deliveries', 'unassigned', 'partners', 'stats',
            'status', 'statuses', 'statusLabels', 'partnerId', 'search'
        ));
    }

    /**
     * Delivery details (admin view).
     */
    public function show(OrderDelivery $delivery)
    {
        $this->authorizeAdmin();

        $delivery->load(['order.user', 'order.items', 'deliveryPartner', 'assignedBy']);
        $partners = User::query()
            ->where('account_type', 'delivery_partner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.deliveries.show', [
            'delivery' => $delivery,
            'order' => $delivery->order,
            'partners' => $partners,
        ]);
    }

    /**
     * Reassign an existing delivery to another active partner.
     */
    public function reassign(Request $request, OrderDelivery $delivery)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'delivery_partner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('account_type', 'delivery_partner')],
        ]);

        // Capture the current partner BEFORE the service runs so the success
        // message can tell a real reassignment apart from a no-op re-save
        // (the service itself never re-notifies an unchanged assignment).
        $previousPartnerId = $delivery->delivery_partner_id;

        $this->deliveries->assign($delivery->order, (int) $data['delivery_partner_id'], $request->user());

        if ((int) $delivery->refresh()->delivery_partner_id !== (int) $previousPartnerId) {
            return back()->with('success', 'Delivery for order ' . $delivery->order->order_number . ' reassigned.');
        }

        return back()->with('success', 'Delivery for order ' . $delivery->order->order_number . ' is already assigned to that delivery partner.');
    }

    /**
     * Admin lifecycle fix: mark a stuck delivery as failed.
     */
    public function status(Request $request, OrderDelivery $delivery)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'status' => ['required', Rule::in(OrderDelivery::STATUSES)],
        ]);

        $this->deliveries->transition($delivery, $data['status'], $request->user(), $request->input('notes'));

        return back()->with('success', 'Delivery for order ' . $delivery->order->order_number . ' marked as ' . $delivery->statusLabel() . '.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
