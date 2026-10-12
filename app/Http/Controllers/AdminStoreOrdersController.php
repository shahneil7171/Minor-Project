<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Notifications\StoreAlert;
use App\Services\DeliveryService;
use App\Services\OrderStatusService;
use App\Support\FulfilmentOwner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ADMIN STORE FULFILMENT — the Admin store acting as a seller.
 *
 * KDP MART's Admin store sells its own products (the seed catalog plus
 * anything an admin adds directly). Those products have no seller account, so
 * the seller fulfilment workflow never applied to them and their orders could
 * be approved but never prepared — they displayed "Preparing by Seller"
 * forever. This controller gives the Admin store exactly the fulfilment
 * capabilities a seller has, for the store's OWN lines only:
 *
 *   - see every order containing Admin-owned products,
 *   - see the store's own lines, variants, quantities, prices and status,
 *   - prepare/package them (confirmed -> processing),
 *   - mark them Ready for Pickup (processing -> ready_for_pickup),
 *   - see the assigned delivery partner and the pickup/delivery progress,
 *   - see the full order-status history.
 *
 * It deliberately REUSES the existing order model, the existing per-line
 * readiness column (order_items.seller_ready_at), the existing
 * OrderStatusService lifecycle, the existing DeliveryService and the existing
 * seller order UI patterns. No second status system, no second seller model
 * and no duplicate fulfilment tables are introduced.
 *
 * AUTHORIZATION IS ENFORCED SERVER-SIDE, not just by hiding buttons:
 * every action goes through OrderStatusService, which only permits an admin to
 * take a preparation step when the order's OUTSTANDING lines belong to the
 * store. A seller's unpacked line therefore always blocks the step, so an
 * admin can never prepare a seller's goods and a mixed order can never become
 * Ready for Pickup early.
 */
class AdminStoreOrdersController extends Controller
{
    public function __construct(
        private OrderStatusService $statuses,
        private DeliveryService $deliveries,
    ) {
    }

    /**
     * Orders that contain at least one Admin-store-owned line.
     *
     * Mirrors SellerOrdersController::index() — the store simply has no
     * seller_id to filter on, so it filters on the store's own ownership.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');

        $query = Order::query()
            ->whereHas('items', fn ($q) => $q->storeOwned())
            ->with(['user', 'items', 'delivery']);

        if ($status !== 'all' && in_array($status, Order::STATUSES, true)) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(15);
        $orders->appends($request->query());

        $statuses = Order::STATUSES;
        $statusLabels = Order::STATUS_LABELS;

        $stats = [
            'awaiting_preparation' => Order::query()
                ->whereIn('status', ['confirmed', 'processing'])
                ->whereHas('items', fn ($q) => $q->storeOwned()->whereNull('seller_ready_at'))
                ->count(),
            'ready_for_pickup' => Order::query()
                ->where('status', 'ready_for_pickup')
                ->whereHas('items', fn ($q) => $q->storeOwned())
                ->count(),
            'in_delivery' => Order::query()
                ->whereIn('status', ['assigned', 'picked_up', 'out_for_delivery'])
                ->whereHas('items', fn ($q) => $q->storeOwned())
                ->count(),
        ];

        return view('admin.store-orders.index', compact(
            'orders',
            'status',
            'statuses',
            'statusLabels',
            'stats'
        ));
    }

    /**
     * One order, showing ONLY the Admin store's own lines.
     *
     * The store never sees (nor acts on) a seller's lines here; the fulfilment
     * owner panel still shows each seller's progress so the store knows why a
     * mixed order has not advanced yet.
     */
    public function show(Order $order)
    {
        $this->authorizeAdmin();

        if (! $order->items()->storeOwned()->exists()) {
            abort(404, 'This order contains no ' . FulfilmentOwner::storeOwnerLabel() . ' products.');
        }

        $order->load([
            'user',
            'delivery.deliveryPartner',
            'statusHistories.actor',
            'items.seller',
        ]);

        $storeItems = $order->storeOwnedItems();

        $activePartners = User::query()
            ->where('account_type', 'delivery_partner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.store-orders.show', [
            'order'         => $order,
            'items'         => $storeItems,
            'history'       => $order->statusHistories,
            // Has the store already packed every one of its own lines?
            'storeReady'    => $storeItems->isNotEmpty()
                && $storeItems->every(fn ($item) => $item->isFulfilmentReady()),
            'ownerProgress' => $order->fulfilmentOwnerProgress(),
            'activePartners' => $activePartners,
        ]);
    }

    /**
     * The store's fulfilment actions: start preparing, or mark Ready for
     * Pickup. Exactly the seller workflow, restricted to the store's own lines.
     *
     * Both steps are re-validated server-side by OrderStatusService, which
     * enforces the lifecycle, the admin's ownership of the outstanding lines
     * and the "no owner may skip a step" rule.
     */
    public function status(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        // The store may only ever act on orders that carry its own products.
        if (! $order->items()->storeOwned()->exists()) {
            abort(404, 'This order contains no ' . FulfilmentOwner::storeOwnerLabel() . ' products.');
        }

        $admin = $request->user();

        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $data['status'];

        if (! in_array($newStatus, ['processing', 'ready_for_pickup'], true)) {
            abort(403, 'The admin store may only prepare its own products or mark them Ready for Pickup.');
        }

        $note = $data['note'] ?? match ($newStatus) {
            'processing'       => FulfilmentOwner::storeOwnerLabel() . ' started preparing the order.',
            'ready_for_pickup' => 'Products packed by the ' . FulfilmentOwner::storeOwnerLabel() . ' and ready for pickup.',
            default            => 'Status updated by the admin store.',
        };

        // Stamp the STORE's own lines as packed before asking the shared order
        // to become Ready for Pickup. Only unstamped store lines are touched,
        // so a double-click can never re-stamp (and never re-notify). The
        // stamp persists even when the order cannot advance yet — that is what
        // makes a mixed order "partially ready".
        if ($newStatus === 'ready_for_pickup' && $order->status === 'processing') {
            $order->items()->storeOwned()->whereNull('seller_ready_at')->update([
                'seller_ready_at' => now(),
            ]);

            $order->refresh()->load('items');

            // A seller line is still unpacked: leave the shared order in
            // Processing and tell the admins how far along it is.
            if (! $order->allFulfilmentOwnersReady()) {
                return $this->reportPartiallyReady($order);
            }
        }

        try {
            $this->statuses->transition($order, $newStatus, $admin, $note);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        }

        if ($newStatus === 'ready_for_pickup') {
            $this->announceReadyForPickup($order, $admin);
        }

        return back()->with(
            'success',
            'Order ' . $order->order_number . ' marked as ' . $order->refresh()->statusLabel() . '.'
        );
    }

    /**
     * The store has packed its portion but another owner has not: keep the
     * shared order in Processing and tell the admins how far along it is.
     */
    private function reportPartiallyReady(Order $order)
    {
        $progress = $order->fulfilmentOwnerProgress();
        $ready    = count(array_filter($progress, fn ($owner) => $owner['ready']));
        $total    = count($progress);

        $this->notifyAdmins(
            'Store Items Ready — Waiting for Other Sellers',
            'The ' . FulfilmentOwner::storeOwnerLabel() . ' has packed its items for order #'
                . $order->order_number . ' (' . $ready . ' of ' . $total . ' fulfilment owners ready). '
                . 'The order becomes Ready for Pickup once every owner is done.',
            $order,
            'order_store_items_ready'
        );

        return back()->with(
            'success',
            'Your store items in order ' . $order->order_number . ' are marked ready for pickup ('
                . $ready . ' of ' . $total . ' fulfilment owners ready). The order will move to Ready for '
                . 'Pickup once every owner has finished packing.'
        );
    }

    /**
     * Announce that the store's parcel is packed: move an already-assigned
     * delivery forward, ask an administrator to assign a partner, and tell the
     * customer. Every side effect is guarded by the underlying service's own
     * idempotency so a retry cannot duplicate it.
     */
    private function announceReadyForPickup(Order $order, User $admin): void
    {
        $order->refresh()->load('delivery');

        if ($order->delivery) {
            // An already-assigned delivery moves forward and alerts its partner
            // (DeliveryService notifies only on a real status change).
            $this->deliveries->markReadyForPickup($order->delivery);

            return;
        }

        // Nobody is carrying it yet: ask the OTHER administrators to assign a
        // delivery partner (the acting admin just did the packing).
        $this->notifyAdmins(
            'Order Ready for Pickup',
            'Order #' . $order->order_number . ' is packed by the ' . FulfilmentOwner::storeOwnerLabel()
                . ' and ready for pickup. Please assign a delivery partner.',
            $order,
            'order_ready_for_pickup',
            $admin->id
        );

        $order->user?->notify(new StoreAlert(
            'Order ready for pickup',
            'Your order #' . $order->order_number . ' has been packed and is ready for pickup.',
            route('orders.show', ['order' => $order->id]),
        ));
    }

    /**
     * In-app alert for every administrator, optionally excluding the actor.
     */
    private function notifyAdmins(string $title, string $body, Order $order, string $type, ?int $exceptId = null): void
    {
        User::query()
            ->where('account_type', 'admin')
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->get()
            ->each(fn (User $admin) => $admin->notify(new StoreAlert(
                $title,
                $body,
                route('admin.orders.show', $order),
                [
                    'type'         => $type,
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                ],
            )));
    }

    /**
     * Only admins may fulfil the Admin store's own products.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
