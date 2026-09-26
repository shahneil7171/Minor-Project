@extends('layouts.app')

@section('title', 'Order #' . $order->order_number)

@section('content')
<style>
    /* Seller order pages are ordinary STOREFRONT pages (they extend
       layouts.app, whose theme is light). The cards below therefore use the
       same light tokens as the rest of the storefront instead of hard-coded
       dark navy surfaces. --light-bg / --dark-text / --muted-text /
       --primary-color all come from layouts/app.blade.php. */
    .seller-order-wrap { max-width: 1000px; margin: 30px auto; padding: 0 15px; }
    .seller-order-wrap h1 { margin-bottom: 20px; color: #111827; font-weight: 800; font-size: 1.875rem; }

    .seller-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        color: #374151;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.06);
    }
    .seller-card h3 { margin-top: 0; margin-bottom: 14px; color: #111827; font-size: 1.1rem; font-weight: 700; }
    .seller-card p { margin: 8px 0; color: #374151; font-size: 0.95rem; }
    .seller-card p strong { color: #111827; }
    .seller-secondary { color: #6B7280; }
    .seller-link { color: var(--primary-color, #667eea); font-weight: 600; }

    .seller-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .seller-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 700; font-size: 0.95rem; color: #ffffff; cursor: pointer; text-decoration: none; transition: opacity 0.15s ease; }
    .seller-btn:hover { opacity: 0.92; }
    .seller-btn:focus-visible { outline: 3px solid rgba(102, 126, 234, 0.45); outline-offset: 2px; }

    .seller-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .seller-table { width: 100%; min-width: 520px; border-collapse: collapse; }
    .seller-table th { padding: 10px; color: #6B7280; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; border-bottom: 1px solid #E5E7EB; }
    .seller-table td { padding: 10px; color: #374151; font-size: 0.95rem; vertical-align: middle; }
    .seller-table tbody tr { border-top: 1px solid #E5E7EB; }
    .seller-table tbody tr:first-child { border-top: none; }
    .seller-item-title { color: #111827; font-weight: 600; }
    .seller-item-options { color: #6B7280; font-size: 0.8rem; margin-top: 2px; }

    .seller-info-banner { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 8px; background: rgba(102, 126, 234, 0.08); border: 1px solid rgba(102, 126, 234, 0.30); color: #4338ca; font-size: 0.92rem; }
    .seller-info-banner strong { color: #3730a3; }

    .seller-alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
    .seller-alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .seller-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    /* Tablet */
    @media (max-width: 992px) {
        .seller-grid-2 { grid-template-columns: 1fr; }
    }
    /* Mobile */
    @media (max-width: 768px) {
        .seller-order-wrap { margin: 20px auto; padding: 0 12px; }
        .seller-order-wrap h1 { font-size: 1.5rem; }
        .seller-card { padding: 16px; margin-bottom: 18px; }
        .seller-grid-2 { gap: 14px; }
        .seller-btn { width: 100%; padding: 12px 16px; }
    }
</style>

<div class="seller-order-wrap">
    <h1>Order #{{ $order->order_number }}</h1>

    @if (session('success'))
        <div class="seller-alert seller-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="seller-alert seller-alert-error">{{ session('error') }}</div>
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
        {{-- theme="light": the timeline keeps its exact status colours and
             steps, rendered for the white card it now sits on. --}}
        <x-order-status-timeline :order="$order" :history="$history" theme="light" />
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
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>
                                @if ($item->product_image)
                                    <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_title }}" style="width:50px; height:50px; object-fit:cover; border-radius:6px; vertical-align:middle; margin-right:10px;">
                                @endif
                                <span class="seller-item-title">{{ $item->product_title }}</span>
                                @if ($item->options_text)
                                    <div class="seller-item-options">{{ $item->options_text }}</div>
                                @endif
                            </td>
                            <td class="seller-secondary">{{ $item->sku }}</td>
                            <td style="text-align:center;">{{ $item->quantity }}</td>
                            <td style="text-align:right; color:#111827; font-weight:600;">${{ number_format($item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
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
