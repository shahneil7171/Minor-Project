@extends('layouts.app')

@section('title', 'Order #{{ $order->order_number }}')

@section('content')
<div class="container" style="max-width: 1000px; margin: 30px auto; padding: 0 15px;">
    <h1 style="margin-bottom: 20px;">Order #{{ $order->order_number }}</h1>

    @if (session('success'))
        <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:#064e3b; color:#d1fae5;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:#7f1d1d; color:#fecaca;">{{ session('error') }}</div>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
        <div style="background:#111827; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px;">
            <h3 style="margin-top:0;">Customer Information</h3>
            <p><strong>Name:</strong> {{ $order->user->name ?? $order->shipping_name }}</p>
            <p><strong>Phone:</strong> {{ $order->shipping_phone }}</p>
            <p><strong>Address:</strong> {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}</p>
        </div>
        <div style="background:#111827; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px;">
            <h3 style="margin-top:0;">Order Information</h3>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
            <p><strong>Status:</strong> <span style="padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold; background:#1e40af; color:#bfdbfe;">{{ $order->statusLabel() }}</span></p>
            <p><strong>Total Lines:</strong> {{ $items->count() }}</p>
        </div>
    </div>

    @if ($order->delivery)
        <div style="background:#111827; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:25px;">
            <h3 style="margin-top:0;">Delivery Information</h3>
            <p><strong>Delivery Partner:</strong> {{ $order->delivery->deliveryPartner->name ?? 'Unknown' }}</p>
            <p><strong>Delivery Status:</strong> {{ $order->delivery->statusLabel() }}</p>
        </div>
    @endif

    <div style="background:#111827; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:25px;">
        <h3 style="margin-top:0;">Your Products in This Order</h3>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="color:#94a3b8; font-size:0.8rem; text-transform:uppercase;">
                    <th style="padding:10px; text-align:left;">Product</th>
                    <th style="padding:10px; text-align:left;">SKU</th>
                    <th style="padding:10px; text-align:center;">Qty</th>
                    <th style="padding:10px; text-align:right;">Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="padding:10px;">
                            @if ($item->product_image)
                                <img src="{{ asset('storage/' . $item->product_image) }}" alt="{{ $item->product_title }}" style="width:50px; height:50px; object-fit:cover; border-radius:6px; vertical-align:middle; margin-right:10px;">
                            @endif
                            {{ $item->product_title }}
                            @if ($item->options_text)
                                <div style="color:#94a3b8; font-size:0.8rem;">{{ $item->options_text }}</div>
                            @endif
                        </td>
                        <td style="padding:10px; color:#94a3b8;">{{ $item->sku }}</td>
                        <td style="padding:10px; text-align:center;">{{ $item->quantity }}</td>
                        <td style="padding:10px; text-align:right;">${{ number_format($item->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (in_array($order->status, ['approved', 'processing', 'packed']))
        <div style="background:#111827; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px;">
            <h3 style="margin-top:0;">Update Status</h3>
            <form method="POST" action="{{ route('seller.orders.status', $order) }}">
                @csrf
                <div style="display:flex; gap:10px; align-items:center;">
                    <select name="status" style="padding:10px 14px; border-radius:8px; border:1px solid #374151; background:#080d1c; color:#e5e7eb; font-weight:600;">
                        @if ($order->status === 'approved')
                            <option value="processing">Start Processing</option>
                        @endif
                        @if (in_array($order->status, ['approved', 'processing']))
                            <option value="packed">Mark Packed (Ready for Pickup)</option>
                        @endif
                    </select>
                    <button type="submit" style="padding:10px 20px; border:none; border-radius:8px; background:#2563eb; color:white; font-weight:700; cursor:pointer;">Update</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
