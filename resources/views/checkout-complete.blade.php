<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Complete | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:linear-gradient(135deg, #08111f 0%, #111827 48%, #123a6f 100%); color:#f8fafc; padding:24px; }
        .container { max-width:920px; margin:0 auto; border-radius:20px; padding:24px; background:rgba(16,24,39,0.88); border:1px solid rgba(255,255,255,0.14); box-shadow:0 24px 50px rgba(0,0,0,0.28); }
        .success { padding:18px; border-radius:16px; background:rgba(16,185,129,0.13); border:1px solid rgba(52,211,153,0.35); margin-bottom:18px; }
        .success h1 { margin:0 0 8px; }
        .success p { margin:0; color:#d1fae5; }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
        .panel { border-radius:16px; padding:18px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.11); margin-bottom:16px; }
        .panel h2 { margin:0 0 12px; font-size:1.05rem; }
        .info { display:grid; gap:8px; color:#cbd5e1; }
        .info strong { color:#fff; }
        .item { display:grid; grid-template-columns:58px 1fr auto; gap:12px; align-items:center; padding:11px 0; border-bottom:1px solid rgba(255,255,255,0.08); }
        .item:last-child { border-bottom:none; }
        .item img { width:58px; height:58px; object-fit:cover; border-radius:10px; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.06); }
        .title { font-weight:900; }
        .meta { color:#94a3b8; font-size:.84rem; margin-top:3px; }
        .amount { font-weight:900; white-space:nowrap; }
        .totals { max-width:360px; margin-left:auto; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:7px 0; color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,0.08); }
        .row.total { color:#fff; font-weight:900; font-size:1.1rem; border-bottom:none; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 15px; border-radius:10px; text-decoration:none; font-weight:800; color:white; background:linear-gradient(135deg, #2563eb, #1d4ed8); }
        .btn.green { background:linear-gradient(135deg, #10b981, #059669); }
        @media (max-width:720px) {
            body { padding:14px; }
            .grid { grid-template-columns:1fr; }
            .item { grid-template-columns:50px 1fr; }
            .item .amount { grid-column:2; }
        }
    </style>
</head>
<body>
@php $format = fn ($amount) => number_format((float) $amount, 2); @endphp
<div class="container">
    <div class="success">
        <h1>Order completed</h1>
        <p>Thank you. Order #{{ $order->order_number }} has been placed with {{ $order->payment_method }}.</p>
    </div>

    <div class="grid">
        <section class="panel">
            <h2>Customer</h2>
            <div class="info">
                <div><strong>Name:</strong> {{ $order->shipping_name }}</div>
                <div><strong>Email:</strong> {{ $order->customer_email ?? 'Guest checkout' }}</div>
                <div><strong>Phone:</strong> {{ $order->shipping_phone }}</div>
            </div>
        </section>

        <section class="panel">
            <h2>Delivery</h2>
            <div class="info">
                <div><strong>Method:</strong> {{ $order->shipping_method ?? 'Standard Delivery' }}</div>
                <div><strong>Address:</strong> {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} - {{ $order->shipping_pincode }}, {{ $order->shipping_country }}</div>
            </div>
        </section>
    </div>

    <section class="panel">
        <h2>Items ordered</h2>
        @foreach ($order->items as $item)
            @php
                $image = $item->product_image;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $image = asset(ltrim($image, '/'));
                }
            @endphp
            <div class="item">
                @if ($image)
                    <img src="{{ $image }}" alt="{{ $item->product_title }}">
                @else
                    <div style="width:58px;height:58px;border-radius:10px;background:rgba(255,255,255,0.06);"></div>
                @endif
                <div>
                    <div class="title">{{ $item->product_title }}</div>
                    @if ($item->options_text)
                        <div class="meta">{{ $item->options_text }}</div>
                    @endif
                    <div class="meta">Qty {{ $item->quantity }} x &#8377;{{ $format($item->price) }}</div>
                </div>
                <div class="amount">&#8377;{{ $format($item->subtotal) }}</div>
            </div>
        @endforeach
    </section>

    <section class="panel">
        <h2>Total</h2>
        <div class="totals">
            <div class="row"><span>Subtotal</span><span>&#8377;{{ $format($order->subtotal) }}</span></div>
            <div class="row"><span>Shipping</span><span>&#8377;{{ $format($order->shipping_cost) }}</span></div>
            <div class="row"><span>Tax</span><span>&#8377;{{ $format($order->tax) }}</span></div>
            @if ((float) $order->discount_amount > 0)
                <div class="row" style="color:#86efac;"><span>Coupon {{ $order->coupon_code ? '(' . $order->coupon_code . ')' : '' }}</span><span>-&#8377;{{ $format($order->discount_amount) }}</span></div>
            @endif
            <div class="row total"><span>Total amount</span><span>&#8377;{{ $format($order->total) }}</span></div>
        </div>
    </section>

    <div class="actions">
        <a class="btn green" href="{{ route('home') }}">Continue shopping</a>
        @auth
            @if ($order->user_id === auth()->id())
                <a class="btn" href="{{ route('orders.show', $order) }}">Track order</a>
                <a class="btn" href="{{ route('orders.index') }}">My Orders</a>
            @endif
        @endauth
    </div>
</div>
</body>
</html>
