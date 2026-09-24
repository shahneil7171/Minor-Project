@extends('layouts.app')

@section('title', 'Order #' . $order->order_number)

@section('content')
<style>
    .seller-order-wrap { max-width: 1000px; margin: 30px auto; padding: 0 15px; }
    .seller-order-wrap h1 { margin-bottom: 20px; color: #0f172a; font-weight: 800; font-size: 1.875rem; }
    .seller-card { background: #111827; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 20px; margin-bottom: 25px; color: #cbd5e1; }
    .seller-card h3 { margin-top: 0; margin-bottom: 14px; color: #ffffff; font-size: 1.1rem; font-weight: 700; }
    .seller-card p { margin: 8px 0; color: #cbd5e1; font-size: 0.95rem; }
    .seller-card p strong { color: #f8fafc; }
    .seller-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .seller-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 700; font-size: 0.95rem; color: #ffffff; cursor: pointer; text-decoration: none; transition: opacity 0.15s ease; }
    .seller-btn:hover { opacity: 0.92; }
    .seller-table { width: 100%; border-collapse: collapse; }
    .seller-table th { padding: 10px; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .seller-table td { padding: 10px; color: #e2e8f0; font-size: 0.95rem; }
    .seller-info-banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 8px; background: rgba(37, 99, 235, 0.12); border: 1px solid rgba(37, 99, 235, 0.35); color: #93c5fd; font-size: 0.92rem; }
    @media (max-width: 768px) {
        .seller-grid-2 { grid-template-columns: 1fr; }
        .seller-table th:nth-child(2), .seller-table td:nth-child(2) { display: none; }
    }
</style>

<div class="seller-order-wrap">
    <h1>Order #{{ $order->order_number }}</h1>

    @if (session('success'))
        <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:#064e3b; color:#d1fae5; font-weight:600;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:#7f1d1d; color:#fecaca; font-weight:600;">{{ session('error') }}</div>
    @endif

    <div class="seller-grid-2">
        <div class="seller-card" style="margin-bottom:0;">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> {{ $order->user->name ?? $order->shipping_name }}</p>
            <p><strong>Phone:</strong> {{ $order->shipping_phone }}</p>
            <p><strong>Address:</strong> {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}</p>
        </div>
        <div class="seller-card" style="margin-bottom:0;">
            <h3>Order Information</h3>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
            <p><strong>Status:</strong> <x-order-status-badge :status="$order->status" /></p>
            <p><strong>Total Lines:</strong> {{ $items->count() }}</p>
        </div>
    </div>

    <div class="seller-card">
        <h3>Order Progress</h3>
        <x-order-status-timeline :order="$order" :history="$history" />
    </div>

    @if ($order->delivery)
        <div class="seller-card">
            <h3>Delivery Information</h3>
            <p><strong>Delivery Partner:</strong> {{ $order->delivery->deliveryPartner->name ?? 'Unknown' }}</p>
            <p><strong>Delivery Status:</strong> {{ $order->delivery->statusLabel() }}</p>
        </div>
    @endif

    <div class="seller-card">
        <h3>Your Products in This Order</h3>
        <table class="seller-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Product</th>
                    <th style="text-align:left;">SKU</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td>
                            @if ($item->product_image)
                                <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_title }}" style="width:50px; height:50px; object-fit:cover; border-radius:6px; vertical-align:middle; margin-right:10px;">
                            @endif
                            <span style="color:#ffffff; font-weight:600;">{{ $item->product_title }}</span>
                            @if ($item->options_text)
                                <div style="color:#94a3b8; font-size:0.8rem;">{{ $item->options_text }}</div>
                            @endif
                        </td>
                        <td style="color:#94a3b8;">{{ $item->sku }}</td>
                        <td style="text-align:center;">{{ $item->quantity }}</td>
                        <td style="text-align:right; color:#ffffff; font-weight:600;">${{ number_format($item->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($order->status === 'confirmed')
        <div class="seller-card">
            <h3>Order Preparation</h3>
            <p style="margin-bottom:16px;">This order has been confirmed by the admin and is ready for you to prepare. Click below to start packing the items.</p>
            <form method="POST" action="{{ route('seller.orders.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="processing">
                <button type="submit" class="seller-btn" style="background:#0891b2;">
                    <i class="fas fa-boxes-packing"></i> Start Processing
                </button>
            </form>
        </div>
    @elseif ($order->status === 'processing')
        <div class="seller-card">
            <h3>Order Packaging</h3>
            <p style="margin-bottom:16px;">Items are being prepared. Once packing is finished and the parcel is ready for handover, mark it Ready for Pickup so the admin can assign a delivery partner.</p>
            <form method="POST" action="{{ route('seller.orders.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="ready_for_pickup">
                <button type="submit" class="seller-btn" style="background:#4f46e5;">
                    <i class="fas fa-check-circle"></i> Mark Ready for Pickup
                </button>
            </form>
        </div>
    @elseif ($order->status === 'ready_for_pickup')
        <div class="seller-card">
            <h3>Next Step</h3>
            <div class="seller-info-banner">
                <i class="fas fa-clock" style="font-size:1.1rem;"></i>
                <span>Order is marked <strong>Ready for Pickup</strong>. Waiting for the administrator to assign a delivery partner.</span>
            </div>
        </div>
    @elseif (in_array($order->status, ['assigned', 'picked_up', 'out_for_delivery'], true))
        <div class="seller-card">
            <h3>Delivery in Progress</h3>
            <div class="seller-info-banner">
                <i class="fas fa-truck" style="font-size:1.1rem;"></i>
                <span>A delivery partner is handling this parcel ({{ $order->delivery->deliveryPartner->name ?? 'Assigned' }}). You will be notified as the package moves.</span>
            </div>
        </div>
    @endif
</div>
@endsection
