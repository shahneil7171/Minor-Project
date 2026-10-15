@extends('admin.layouts.panel')

@section('title', 'Store Order #' . $order->order_number)

@php
    /**
     * ADMIN STORE FULFILMENT — one order, showing ONLY the store's own lines.
     *
     * The store prepares and dispatches its own products here, exactly as a
     * seller does on their order page. The fulfilment owner shown everywhere is
     * resolved by App\Support\FulfilmentOwner, so a mixed order correctly names
     * the store AND its sellers instead of blaming a seller for the store's
     * products.
     */
    $storeLabel = \App\Support\FulfilmentOwner::storeOwnerLabel();
    $storePending = $items->contains(fn ($item) => ! $item->isFulfilmentReady());
@endphp

@push('styles')
<style>
    .od-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:18px; }
    .od-card { background:var(--ka-panel); border:1px solid var(--ka-border); border-radius:12px; padding:18px 20px; margin-bottom:18px; }
    .od-card h3 { margin:0 0 12px; color:#fff; font-size:1rem; }
    .od-row { display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid rgba(255,255,255,.06); font-size:.88rem; }
    .od-row:last-child { border-bottom:none; }
    .od-row span:first-child { color:var(--ka-muted); }
    .od-row span:last-child { text-align:right; font-weight:600; }
    .od-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .od-actions form { display:inline-flex; gap:8px; align-items:center; margin:0; }
    .od-actions select { padding:9px 11px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text); font-weight:600; }
    .od-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:9px; border:none; color:#fff; font-weight:700; font-size:.86rem; cursor:pointer; text-decoration:none; }
    .od-note { margin:10px 0 0; color:var(--ka-muted); font-size:.82rem; }
    .od-hint { margin-top:14px; padding:12px 14px; border-radius:10px; background:rgba(148,163,184,.10); border:1px solid rgba(148,163,184,.28); color:var(--ka-muted); font-size:.82rem; }
    .od-banner { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:9px; background:rgba(37,99,235,.10); border:1px solid rgba(37,99,235,.32); color:#bfdbfe; font-size:.88rem; }
    .od-banner strong { color:#dbeafe; }
    .od-items { width:100%; border-collapse:collapse; }
    .od-items th, .od-items td { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.06); text-align:left; font-size:.88rem; }
    .od-items th { color:var(--ka-muted); text-transform:uppercase; font-size:.72rem; letter-spacing:.05em; }
    .od-ready { color:#34d399; font-weight:700; }
    .od-pending { color:#fbbf24; font-weight:700; }
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h2>Store Order #{{ $order->order_number }}</h2>
        <p>
            Placed {{ $order->created_at->format('M d, Y h:i A') }}
            &nbsp;·&nbsp; Customer: {{ $order->user->name ?? $order->shipping_name }}
            &nbsp;·&nbsp; Fulfilled by: <strong>{{ $storeLabel }}</strong>
        </p>
    </div>
    <div class="row-actions">
        <a class="primary" href="{{ route('admin.store-orders.index') }}">Store Fulfilment</a>
        <a href="{{ route('admin.orders.index') }}">All orders</a>
        <a href="{{ route('admin.orders.show', $order) }}">Full order</a>
        <a href="{{ route('admin.orders.invoice', $order) }}">Invoice</a>
        @if ($order->delivery)
            <a href="{{ route('admin.deliveries.show', $order->delivery) }}">Delivery record</a>
        @endif
    </div>
</div>

@if (session('error'))
    <div class="ka-flash error">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="ka-flash error">{{ $errors->first() }}</div>
@endif

<div class="od-card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="color:var(--ka-muted); font-size:.82rem; text-transform:uppercase; letter-spacing:.06em;">Current status</span>
            <x-order-status-badge :status="$order->status" />
        </div>
        @if ($order->delivery)
            <span style="color:var(--ka-muted); font-size:.82rem; display:inline-flex; align-items:center; gap:8px;">
                Delivery: <x-order-status-badge :status="$order->delivery->status" />
                @if ($order->delivery->deliveryPartner)
                    &nbsp;· {{ $order->delivery->deliveryPartner->name }}
                @endif
            </span>
        @endif
    </div>

    <x-order-status-timeline :order="$order" :history="$history" />
</div>

<div class="od-card">
    <h3>Store Preparation</h3>

    @if ($order->status === 'pending')
        <div class="od-banner">
            <i class="fas fa-clock" style="font-size:1.05rem;"></i>
            <span>
                This order is <strong>awaiting admin approval</strong>. Once it is confirmed the store can
                prepare and package its items here.
            </span>
        </div>
    @elseif ($order->status === 'confirmed')
        <p class="od-note" style="margin:0 0 14px;">
            Confirmed by an admin. The {{ $storeLabel }} products in this order are ready to be prepared
            and packaged.
        </p>
        <div class="od-actions">
            <form method="POST" action="{{ route('admin.store-orders.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="processing">
                <button type="submit" class="od-btn" style="background:#0891b2;">
                    <i class="fas fa-boxes-packing"></i> Start Preparing
                </button>
            </form>
        </div>
    @elseif ($order->status === 'processing')
        @if ($storePending)
            <p class="od-note" style="margin:0 0 14px;">
                The store items are being prepared. Once packing is finished and the parcel is ready for
                handover, mark them Ready for Pickup so a delivery partner can be assigned.
            </p>
            <div class="od-actions">
                <form method="POST" action="{{ route('admin.store-orders.status', $order) }}"
                      onsubmit="return confirm('Mark the store items in order #{{ $order->order_number }} as packed and Ready for Pickup?');">
                    @csrf
                    <input type="hidden" name="status" value="ready_for_pickup">
                    <button type="submit" class="od-btn" style="background:#4f46e5;">
                        <i class="fas fa-check-circle"></i> Mark Ready for Pickup
                    </button>
                </form>
            </div>
        @else
            <div class="od-banner">
                <i class="fas fa-check-circle" style="font-size:1.1rem;"></i>
                <span>
                    The store items are <strong>packed</strong>. The order moves to Ready for Pickup as soon as
                    every other fulfilment owner has finished packing too.
                </span>
            </div>
        @endif
    @elseif ($order->status === 'ready_for_pickup')
        <div class="od-banner">
            <i class="fas fa-warehouse" style="font-size:1.1rem;"></i>
            <span>
                Order is marked <strong>Ready for Pickup</strong>.
                @if ($order->delivery)
                    Delivery partner: <strong>{{ $order->delivery->deliveryPartner->name ?? 'assigned' }}</strong>.
                @else
                    Assign an active delivery partner to move it to "Assigned to Delivery Partner".
                @endif
            </span>
        </div>
    @elseif (in_array($order->status, ['assigned', 'picked_up', 'out_for_delivery'], true))
        <div class="od-banner">
            <i class="fas fa-truck" style="font-size:1.1rem;"></i>
            <span>
                A delivery partner is handling this parcel
                ({{ $order->delivery->deliveryPartner->name ?? 'assigned' }}).
                The delivery workflow now owns the remaining steps.
            </span>
        </div>
    @elseif ($order->status === 'delivered')
        <p class="od-note" style="margin:0;">Delivered. Returns and refunds are handled from the Returns area.</p>
    @elseif ($order->status === 'cancelled')
        <p class="od-note" style="margin:0;">This order was cancelled; no further fulfilment actions are possible.</p>
    @endif

    @if ($order->isCancellable())
        <div class="od-actions" style="margin-top:14px;">
            <form method="POST" action="{{ route('admin.orders.cancel', $order) }}"
                  onsubmit="return confirm('Cancel order #{{ $order->order_number }}?');">
                @csrf
                <button type="submit" class="od-btn" style="background:var(--ka-danger);">Cancel Order</button>
            </form>
        </div>
    @endif
</div>

<div class="od-card">
    <h3>Fulfilment Owners</h3>
    <p class="od-note" style="margin:0 0 12px;">
        Every owner prepares their own items. The order only becomes Ready for Pickup once all of them
        have finished packing — including this store.
    </p>
    @foreach ($ownerProgress as $owner)
        <div class="od-row">
            <span>
                {{ $owner['name'] }}
                <span style="color:var(--ka-muted); font-weight:400;">({{ $owner['quantity'] }} item{{ $owner['quantity'] === 1 ? '' : 's' }})</span>
            </span>
            <span>
                @if ($owner['ready'])
                    <span class="od-ready">Ready since {{ optional($owner['ready_at'])->format('M d, Y h:i A') }}</span>
                @else
                    <span class="od-pending">Preparing</span>
                @endif
            </span>
        </div>
    @endforeach
</div>

<div class="od-card">
    <h3>{{ $storeLabel }} items ({{ $items->count() }})</h3>
    <table class="od-items">
        <thead>
            <tr>
                <th>Product</th>
                <th>Fulfilled by</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
                <th>Fulfilment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>
                        {{ $item->product_title }}
                        @if ($item->options_text)
                            <div style="color:var(--ka-muted); font-size:.78rem;">{{ $item->options_text }}</div>
                        @endif
                    </td>
                    <td>{{ $item->fulfilmentOwnerName() }}</td>
                    <td>{{ $item->sku ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>@orderMoney($order, (float) $item->price)</td>
                    <td>@orderMoney($order, (float) $item->subtotal)</td>
                    <td>
                        @if ($item->isFulfilmentReady())
                            <span class="od-ready">Ready {{ optional($item->seller_ready_at)->format('M d, h:i A') }}</span>
                        @else
                            <span class="od-pending">Preparing</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="od-row" style="margin-top:10px;"><span>Amount paid</span><span><strong>@orderMoney($order, $order->amountDue())</strong></span></div>
    @if ((float) $order->coins_redeemed > 0)
        <div class="od-row" style="font-size:0.85rem; color:#94a3b8;"><span>Original total minus {{ rtrim(rtrim(number_format((float) $order->coins_redeemed, 2), '0'), '.') }} Smart Coins</span><span>@orderMoney($order, $order->originalTotal()) &minus; @orderMoney($order, $order->coinsValue())</span></div>
    @endif
</div>

<div class="od-grid">
    <div class="od-card">
        <x-order-status-history :history="$history" />
    </div>

    <div class="od-card">
        <h3>Customer &amp; shipping</h3>
        <div class="od-row"><span>Name</span><span>{{ $order->shipping_name }}</span></div>
        <div class="od-row"><span>Email</span><span>{{ $order->user->email ?? $order->customer_email ?? '—' }}</span></div>
        <div class="od-row"><span>Phone</span><span>{{ $order->shipping_phone }}</span></div>
        <div class="od-row"><span>Address</span><span>{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}{{ $order->shipping_country ? ', ' . $order->shipping_country : '' }}</span></div>
        <div class="od-row"><span>Shipping method</span><span>{{ $order->shipping_method ?? 'Standard Delivery' }}</span></div>
        <div class="od-row"><span>Payment</span><span>{{ $order->payment_method ?? 'Cash on Delivery' }}</span></div>
        @if ($order->notes)
            <div class="od-row"><span>Notes</span><span>{{ $order->notes }}</span></div>
        @endif
    </div>
</div>

@if ($order->delivery)
    <div class="od-card">
        <h3>Delivery</h3>
        <div class="od-row"><span>Partner</span><span>{{ $order->delivery->deliveryPartner->name ?? 'Unassigned' }}</span></div>
        <div class="od-row"><span>Delivery status</span><span>{{ $order->delivery->statusLabel() }}</span></div>
        <div class="od-row"><span>Assigned</span><span>{{ optional($order->delivery->assigned_at)->format('M d, Y h:i A') ?? '—' }}</span></div>
        <div class="od-row"><span>Picked up</span><span>{{ optional($order->delivery->picked_up_at)->format('M d, Y h:i A') ?? '—' }}</span></div>
        <div class="od-row"><span>Out for delivery</span><span>{{ optional($order->delivery->out_for_delivery_at)->format('M d, Y h:i A') ?? '—' }}</span></div>
        <div class="od-row"><span>Delivered</span><span>{{ optional($order->delivery->delivered_at)->format('M d, Y h:i A') ?? '—' }}</span></div>
        @if ($order->delivery->delivery_notes)
            <div class="od-row"><span>Notes</span><span>{{ $order->delivery->delivery_notes }}</span></div>
        @endif
    </div>
@endif
@endsection
