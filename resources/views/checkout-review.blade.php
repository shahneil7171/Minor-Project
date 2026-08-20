<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Order | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:linear-gradient(135deg, #08111f 0%, #111827 48%, #123a6f 100%); color:#f8fafc; padding:24px; }
        .container { max-width:980px; margin:0 auto; border-radius:20px; padding:24px; background:rgba(16,24,39,0.88); border:1px solid rgba(255,255,255,0.14); box-shadow:0 24px 50px rgba(0,0,0,0.28); }
        .header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
        .header h1 { margin:0 0 6px; }
        .header p { margin:0; color:#cbd5e1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:11px 16px; min-height:42px; border-radius:10px; text-decoration:none; font-weight:800; color:white; background:linear-gradient(135deg, #2563eb, #1d4ed8); border:none; cursor:pointer; }
        .btn.green { background:linear-gradient(135deg, #10b981, #059669); }
        .item { display:grid; grid-template-columns:72px 1fr auto; gap:14px; align-items:center; padding:14px 0; border-bottom:1px solid rgba(255,255,255,0.1); }
        .item img { width:72px; height:72px; object-fit:cover; border-radius:12px; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.06); }
        .title { font-weight:900; }
        .meta { color:#94a3b8; font-size:.88rem; margin-top:4px; }
        .amount { font-weight:900; white-space:nowrap; }
        .totals { max-width:360px; margin-left:auto; margin-top:18px; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,0.08); }
        .row.total { color:#fff; font-weight:900; font-size:1.1rem; border-bottom:none; }
        .actions { margin-top:24px; display:flex; justify-content:flex-end; gap:12px; flex-wrap:wrap; }
        @media (max-width:620px) {
            body { padding:14px; }
            .item { grid-template-columns:58px 1fr; }
            .item .amount { grid-column:2; }
        }
    </style>
</head>
<body>
@php $format = fn ($amount) => number_format((float) $amount, 2); @endphp
<div class="container">
    <div class="header">
        <div>
            <h1>Review Order</h1>
            <p>Check your products and totals before checkout.</p>
        </div>
        <a class="btn" href="{{ route('cart.index') }}">Back to cart</a>
    </div>

    @foreach ($lines as $line)
        @php
            $image = $line['image'] ?? '';
            if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                $image = asset(ltrim($image, '/'));
            }
        @endphp
        <div class="item">
            @if ($image)
                <img src="{{ $image }}" alt="{{ $line['title'] }}">
            @else
                <div style="width:72px;height:72px;border-radius:12px;background:rgba(255,255,255,0.06);"></div>
            @endif
            <div>
                <div class="title">{{ $line['title'] }}</div>
                @if ($line['options_text'])
                    <div class="meta">{{ $line['options_text'] }}</div>
                @endif
                <div class="meta">Qty {{ $line['quantity'] }} x &#8377;{{ $format($line['unit_price']) }}</div>
            </div>
            <div class="amount">&#8377;{{ $format($line['subtotal']) }}</div>
        </div>
    @endforeach

    <div class="totals">
        <div class="row"><span>Subtotal</span><span>&#8377;{{ $format($summary['subtotal']) }}</span></div>
        <div class="row"><span>Shipping</span><span>&#8377;{{ $format($summary['shipping']) }}</span></div>
        <div class="row"><span>Tax</span><span>&#8377;{{ $format($summary['tax']) }}</span></div>
        @if ($summary['discount'] > 0)
            <div class="row" style="color:#86efac;"><span>Discount</span><span>-&#8377;{{ $format($summary['discount']) }}</span></div>
        @endif
        <div class="row total"><span>Total</span><span>&#8377;{{ $format($summary['total']) }}</span></div>
    </div>

    <div class="actions">
        <a class="btn" href="{{ route('cart.index') }}">Back</a>
        <a class="btn green" href="{{ route('checkout.index') }}">Continue to checkout</a>
    </div>
</div>
</body>
</html>
