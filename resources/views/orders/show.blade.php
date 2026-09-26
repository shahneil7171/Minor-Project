<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #{{ $order->order_number }} | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 980px; margin: 0 auto; border-radius: 24px; padding: 26px; background: rgba(2,6,23,0.85); border: 1px solid rgba(255,255,255,0.16); box-shadow: 0 24px 50px rgba(0,0,0,0.28); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 6px; font-size: 1.7rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; background: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.4); color: #cffafe; font-weight: 600; }
        .card { border-radius: 18px; padding: 20px 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 18px; }
        .card h2 { margin: 0 0 14px; font-size: 1.1rem; color: #fff; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: capitalize; }
        .badge.pending { background: rgba(245,158,11,0.16); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .badge.processing { background: rgba(56,189,248,0.16); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.4); }
        .badge.packed { background: rgba(99,102,241,0.18); color: #c7d2fe; border: 1px solid rgba(99,102,241,0.5); }
        .badge.shipped { background: rgba(139,92,246,0.16); color: #c4b5fd; border: 1px solid rgba(139,92,246,0.4); }
        .badge.delivered { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.cancelled { background: rgba(239,68,68,0.16); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
        .timeline { display: flex; justify-content: space-between; gap: 8px; margin: 10px 0 6px; position: relative; }
        .step { flex: 1; text-align: center; position: relative; z-index: 1; }
        .step .dot { width: 18px; height: 18px; border-radius: 999px; margin: 0 auto 8px; border: 2px solid rgba(148,163,184,0.5); background: #0f172a; }
        .step .label { font-size: 0.75rem; color: #94a3b8; font-weight: 700; }
        .step.done .dot { background: #10b981; border-color: #10b981; }
        .step.active .dot { background: #2563eb; border-color: #38bdf8; box-shadow: 0 0 0 5px rgba(56,189,248,0.15); }
        .step.active .label { color: #7dd3fc; }
        .step.done .label { color: #6ee7b7; }
        .timeline-track { height: 3px; background: rgba(148,163,184,0.3); position: absolute; top: 8px; left: 12%; right: 12%; z-index: 0; }
        .cancelled-note { padding: 14px 16px; border-radius: 12px; background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.4); color: #fecaca; font-weight: 600; margin-top: 14px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .info-item { padding: 10px 14px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
        .info-item span { display: block; font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; }
        .info-item strong { font-size: 0.9rem; color: #f8fafc; }
        .item-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .item-row:last-child { border-bottom: none; }
        .item-row img { width: 64px; height: 64px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.05); }
        .item-row .meta { flex: 1; }
        .item-row .meta .title { font-weight: 700; }
        .item-row .meta .opt { color: #7dd3fc; font-size: 0.82rem; margin-top: 2px; }
        .item-row .meta .qty { color: #94a3b8; font-size: 0.85rem; margin-top: 2px; }
        .item-row .amt { font-weight: 800; white-space: nowrap; }
        .return-btn { display:inline-block; margin-top:8px; padding:8px 14px; border-radius:10px; text-decoration:none; font-weight:700; font-size:0.8rem; color:#fecaca; background:rgba(239,68,68,0.14); border:1px solid rgba(239,68,68,0.4); transition:background 0.2s ease; }
        .return-btn:hover { background:rgba(239,68,68,0.28); color:#fff; }
        .return-note { margin-top:6px; color:#94a3b8; font-size:0.78rem; }
        .return-note.expired { color:#fcd34d; font-weight:600; }
        .totals { margin-top: 12px; display: flex; justify-content: flex-end; }
        .totals .box { min-width: 260px; }
        .totals .row { display: flex; justify-content: space-between; padding: 6px 0; color: #cbd5e1; }
        .totals .row.grand { border-top: 1px solid rgba(255,255,255,0.16); margin-top: 6px; padding-top: 12px; color: #fff; font-weight: 800; font-size: 1.05rem; }
        @media (max-width: 560px) { body { padding: 14px; } .timeline .label { font-size: 0.62rem; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <p style="margin:0; color:#94a3b8;">Order details</p>
                <h1 style="margin:4px 0 0;">Order #{{ $order->order_number }}</h1>
            </div>
            @php
                $viewerIsAdmin = auth()->check() && auth()->user()->isAdmin() && $order->user_id !== auth()->id();
                $backUrl = $viewerIsAdmin
                    ? ($order->user_id ? route('admin.customers.show', $order->user_id) : route('admin.orders.index'))
                    : route('orders.index');
                $backLabel = $viewerIsAdmin ? 'Back to customer' : 'Back to my orders';
            @endphp
            <a href="{{ $backUrl }}">{{ $backLabel }}</a>
        </div>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        <div class="card" id="tracking">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <x-order-status-badge :status="$order->status" />
                <span style="color:#cbd5e1; font-size:0.85rem;">
                    Placed {{ $order->created_at->format('M d, Y h:i A') }}
                </span>
            </div>

            <x-order-status-timeline :order="$order" :history="$history" theme="light" />

            @if ($order->delivery?->deliveryPartner)
                <div style="margin-top:6px; color:#cbd5e1; font-size:0.85rem;">
                    Delivery partner: <strong>{{ $order->delivery->deliveryPartner->name }}</strong>
                </div>
            @endif

            @if ($order->isDelivered())
                <div style="margin-top:12px; padding:12px 14px; border-radius:12px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.4); color:#a7f3d0; font-weight:600;">
                    Delivered on {{ optional($order->deliveredAt())->format('M d, Y h:i A') }} — you can request a return for eligible items below.
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Status history</h2>
            <x-order-status-history :history="$history" :title="null" theme="light" />
        </div>

        @if ($canCancel)
            <div class="card">
                <h2>Cancel this order</h2>
                <p style="margin:0 0 12px; color:#cbd5e1; font-size:0.88rem;">
                    Orders can only be cancelled before the seller starts processing them.
                </p>
                <form method="POST" action="{{ route('orders.cancel', $order) }}"
                      onsubmit="return confirm('Cancel order #{{ $order->order_number }}?');">
                    @csrf
                    <button type="submit" style="padding:11px 18px; border:none; border-radius:12px; font-weight:700; color:#fff; cursor:pointer; background:linear-gradient(135deg,#ef4444,#b91c1c);">
                        Cancel Order
                    </button>
                </form>
            </div>
        @endif

        <div class="card">
            <h2>Shipping details</h2>
            <div class="info-grid">
                <div class="info-item"><span>Name</span><strong>{{ $order->shipping_name }}</strong></div>
                <div class="info-item"><span>Phone</span><strong>{{ $order->shipping_phone }}</strong></div>
                <div class="info-item"><span>Address</span><strong>{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }}, {{ $order->shipping_pincode }}{{ $order->shipping_country ? ', ' . $order->shipping_country : '' }}</strong></div>
                <div class="info-item"><span>Shipping</span><strong>{{ $order->shipping_method ?? 'Standard Delivery' }}</strong></div>
                <div class="info-item"><span>Payment</span><strong>{{ $order->payment_method ?? 'Cash on Delivery' }}</strong></div>
                @if ($order->delivery)
                    <div class="info-item"><span>Delivery status</span><strong>{{ $order->delivery->statusLabel() }}</strong></div>
                    @if ($order->delivery->deliveryPartner)
                        <div class="info-item"><span>Delivery partner</span><strong>{{ $order->delivery->deliveryPartner->name }}</strong></div>
                    @endif
                @endif
            </div>
        </div>

        <div class="card">
            <h2>Items ordered ({{ $order->items->count() }})</h2>
            @foreach ($order->items as $item)
                <div class="item-row">
                    @if ($item->product_image)
                        <img src="{{ $item->product_image }}" alt="{{ $item->product_title }}">
                    @endif
                    <div class="meta">
                        <div class="title">{{ $item->product_title }}</div>
                        @if ($item->options_text)
                            <div class="opt">{{ $item->options_text }}</div>
                        @endif
                        <div class="qty">Qty: {{ $item->quantity }}</div>
                        @php $info = $returnInfo[$item->id] ?? null; @endphp
                        @if ($info && $info['eligible'])
                            <a class="return-btn" href="{{ route('returns.create', ['item' => $item->id]) }}">Return Product</a>
                            <div class="return-note">Return available until {{ optional($order->returnDeadline())->format('M d, Y') }}.</div>
                        @elseif ($info && ! $info['eligible'] && $info['reason'] && $order->isDelivered())
                            <div class="return-note expired">{{ $info['reason'] }}</div>
                        @endif
                    </div>
                    <div class="amt">{{ '&#8377;' . number_format((float) $item->price, 2) }}</div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <h2>Order total</h2>
            <div class="totals">
                <div class="box">
                    <div class="row"><span>Subtotal</span><span>&#8377;{{ number_format((float) $order->subtotal, 2) }}</span></div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="row" style="color:#34d399;"><span>Coupon {{ $order->coupon_code ? '(' . $order->coupon_code . ')' : '' }}</span><span>−&#8377;{{ number_format((float) $order->discount_amount, 2) }}</span></div>
                    @endif
                    <div class="row"><span>Shipping</span><span>&#8377;{{ number_format((float) $order->shipping_cost, 2) }}</span></div>
                    <div class="row"><span>Tax</span><span>&#8377;{{ number_format((float) $order->tax, 2) }}</span></div>
                    <div class="row grand"><span>Total</span><span>&#8377;{{ number_format((float) $order->total, 2) }}</span></div>
                </div>
            </div>

            <a href="{{ route('products') }}" style="display:inline-flex; margin-top:18px; padding:11px 18px; border-radius:12px; text-decoration:none; font-weight:700; color:white; background:linear-gradient(135deg, #10b981, #059669);">Continue shopping</a>
        </div>

        <script>
            setTimeout(function () {
                document.querySelectorAll('.flash').forEach(function (el) { el.style.display = 'none'; });
            }, 4000);
        </script>
    </div>
</body>
</html>
