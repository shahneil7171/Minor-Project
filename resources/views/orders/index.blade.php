<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 980px; margin: 0 auto; border-radius: 24px; padding: 26px; background: rgba(2,6,23,0.85); border: 1px solid rgba(255,255,255,0.16); box-shadow: 0 24px 50px rgba(0,0,0,0.28); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 6px; font-size: 1.8rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; background: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.4); color: #cffafe; font-weight: 600; }
        .flash.error { background: rgba(248,113,113,0.12); border-color: rgba(248,113,113,0.4); color: #fecaca; }
        .order-card { border-radius: 18px; padding: 20px 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 16px; }
        .order-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .order-top .num { font-weight: 800; font-size: 1.05rem; }
        .order-top .date { color: #94a3b8; font-size: 0.85rem; margin-top: 2px; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: capitalize; }
        .badge.pending { background: rgba(245,158,11,0.16); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .badge.processing { background: rgba(56,189,248,0.16); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.4); }
        .badge.shipped { background: rgba(139,92,246,0.16); color: #c4b5fd; border: 1px solid rgba(139,92,246,0.4); }
        .badge.delivered { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.cancelled { background: rgba(239,68,68,0.16); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
        .items-preview { color: #cbd5e1; font-size: 0.9rem; margin-bottom: 14px; }
        .order-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .order-foot .total { font-size: 1.1rem; font-weight: 800; }
        .order-foot a.track { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #10b981, #059669); }
        .empty { padding: 52px; text-align: center; border-radius: 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: #cbd5e1; }
        .empty h2 { color: #f8fafc; margin: 0 0 8px; }
        .empty a { display: inline-flex; margin-top: 18px; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #10b981, #059669); }
        .pagination { margin-top: 20px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 13px; border-radius: 10px; text-decoration: none; font-weight: 700; color: #e2e8f0; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.14); }
        .pagination .current { background: #2563eb; border-color: #2563eb; }
        @media (max-width: 560px) { body { padding: 14px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>My Orders</h1>
                <p>Review your past orders and track delivery status.</p>
            </div>
            <a href="{{ route('home') }}">Continue shopping</a>
        </div>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        @if ($orders->count() > 0)
            @foreach ($orders as $order)
                <div class="order-card">
                    <div class="order-top">
                        <div>
                            <div class="num">Order #{{ $order->order_number }}</div>
                            <div class="date">Placed {{ $order->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                        <span class="badge {{ $order->status }}">{{ $order->status }}</span>
                    </div>

                    <div class="items-preview">
                        {{ $order->items->count() }} item(s)
                        @foreach ($order->items as $item)
                            - {{ $item->product_title }}
                        @endforeach
                    </div>

                    <div class="order-foot">
                        <div class="total">Total: &#8377;{{ number_format((float) $order->total, 2) }}</div>
                        <a class="track" href="{{ route('orders.show', $order) }}">View &amp; Track</a>
                    </div>
                </div>
            @endforeach

            @if ($orders->hasPages())
                <div class="pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        @else
            <div class="empty">
                <h2>No orders yet.</h2>
                <p>Once you place an order it will appear here for tracking.</p>
                <a href="{{ route('products') }}">Browse products</a>
            </div>
        @endif
    </div>

    <script>
        setTimeout(function () {
            document.querySelectorAll('.flash').forEach(function (el) { el.style.display = 'none'; });
        }, 4000);
    </script>
</body>
</html>

