@extends('admin.layouts.panel')

@section('title', 'Order #' . $order->order_number)

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
    .od-items { width:100%; border-collapse:collapse; }
    .od-items th, .od-items td { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.06); text-align:left; font-size:.88rem; }
    .od-items th { color:var(--ka-muted); text-transform:uppercase; font-size:.72rem; letter-spacing:.05em; }
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h2>Order #{{ $order->order_number }}</h2>
        <p>
            Placed {{ $order->created_at->format('M d, Y h:i A') }}
            &nbsp;·&nbsp; Customer: {{ $order->user->name ?? $order->shipping_name }}
        </p>
    </div>
    <div class="row-actions">
        <a class="primary" href="{{ route('admin.orders.index') }}">All orders</a>
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
    <h3>Actions</h3>
    <div class="od-actions">
        @if ($order->status === 'pending')
            <form method="POST" data-status-action action="{{ route('admin.orders.approve', $order) }}">
                @csrf
                <button type="submit" class="od-btn" style="background:#059669;">Confirm Order</button>
            </form>
        @endif

        @if ($order->status === 'confirmed')
            <form method="POST" data-status-action action="{{ route('admin.orders.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="processing">
                <button type="submit" class="od-btn" style="background:#0891b2;">Start Processing</button>
            </form>
        @endif

        @if ($order->status === 'processing')
            <form method="POST" data-status-action action="{{ route('admin.orders.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="ready_for_pickup">
                <button type="submit" class="od-btn" style="background:#4f46e5;">Mark Ready for Pickup</button>
            </form>
        @endif

        @if ($order->status === 'ready_for_pickup' && ! $order->delivery)
            <form method="POST" data-status-action action="{{ route('admin.orders.assign-delivery', $order) }}">
                @csrf
                <select name="delivery_partner_id" required>
                    @forelse ($activePartners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                    @empty
                        <option value="" disabled>No active delivery partners</option>
                    @endforelse
                </select>
                <button type="submit" class="od-btn" style="background:#7c3aed;" {{ $activePartners->isEmpty() ? 'disabled' : '' }}>
                    Assign Delivery Partner
                </button>
            </form>
        @endif

        @if ($order->isCancellable())
            <form method="POST" data-status-action action="{{ route('admin.orders.cancel', $order) }}"
                  onsubmit="return confirm('Cancel order #{{ $order->order_number }}?');">
                @csrf
                <button type="submit" class="od-btn" style="background:var(--ka-danger);">Cancel Order</button>
            </form>
        @endif
    </div>

    @if ($order->status === 'pending')
        <p class="od-note">Waiting for review: confirm the order, or cancel it while it is still pending.</p>
    @elseif ($order->status === 'confirmed')
        <p class="od-note">Confirmed. The seller can now start processing; it may also still be cancelled before processing begins.</p>
    @elseif ($order->status === 'processing')
        <p class="od-note">The seller is processing the order. There is no "Deliver" button: a delivery partner cannot be assigned until the order is Ready for Pickup.</p>
    @elseif ($order->status === 'ready_for_pickup')
        <p class="od-note">Ready for pickup. Assign an active delivery partner to move the order to "Assigned to Delivery Partner".</p>
    @elseif (in_array($order->status, ['assigned', 'picked_up', 'out_for_delivery'], true))
        <p class="od-note">Owned by the delivery workflow now: only the assigned delivery partner marks Picked Up, Out for Delivery and Delivered. Admins can still fix a stuck delivery from the Deliveries page.</p>
    @elseif ($order->status === 'delivered')
        <p class="od-note">Delivered is a terminal delivery status. Returns and refunds are handled from the Returns area.</p>
    @elseif ($order->status === 'cancelled')
        <p class="od-note">This order was cancelled; no further status changes are possible.</p>
    @endif

    @if ($order->status === 'ready_for_pickup' && $order->delivery)
        <div class="od-hint">
            A delivery partner is already assigned ({{ $order->delivery->deliveryPartner->name ?? 'unknown' }}).
            Use the delivery record to reassign or to fix a stuck delivery.
        </div>
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

<div class="od-card">
    <h3>Items ({{ $order->items->count() }})</h3>
    <table class="od-items">
        <thead>
            <tr>
                <th>Product</th>
                <th>Seller</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_title }}
                        @if ($item->options_text)
                            <div style="color:var(--ka-muted); font-size:.78rem;">{{ $item->options_text }}</div>
                        @endif
                    </td>
                    <td>{{ $item->seller->name ?? 'KDP MART' }}</td>
                    <td>{{ $item->sku ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>&#8377;{{ number_format((float) $item->price, 2) }}</td>
                    <td>&#8377;{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="od-row" style="margin-top:10px;"><span>Subtotal</span><span>&#8377;{{ number_format((float) $order->subtotal, 2) }}</span></div>
    @if ((float) $order->discount_amount > 0)
        <div class="od-row"><span>Discount {{ $order->coupon_code ? '(' . $order->coupon_code . ')' : '' }}</span><span>-&#8377;{{ number_format((float) $order->discount_amount, 2) }}</span></div>
    @endif
    <div class="od-row"><span>Shipping</span><span>&#8377;{{ number_format((float) $order->shipping_cost, 2) }}</span></div>
    <div class="od-row"><span>Tax</span><span>&#8377;{{ number_format((float) $order->tax, 2) }}</span></div>
    <div class="od-row"><span><strong>Total</strong></span><span><strong>&#8377;{{ number_format((float) $order->total, 2) }}</strong></span></div>
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
