<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order->order_number }} | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #0b1120; color: #e5e7eb; padding: 24px; }
        .toolbar { max-width: 800px; margin: 0 auto 18px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 10px; font-weight: 700; color: white; border: none; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-print { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-back { background: linear-gradient(135deg, #475569, #334155); }
        .invoice { max-width: 800px; margin: 0 auto; background: #111827; border: 1px solid #26304a; border-radius: 18px; padding: 30px 32px; }
        .invoice-header { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; border-bottom: 1px solid #26304a; padding-bottom: 18px; margin-bottom: 18px; }
        .invoice-header h1 { margin: 0; font-size: 1.6rem; color: #fff; letter-spacing: 0.03em; }
        .invoice-header .store { color: #94a3b8; font-size: 0.85rem; margin-top: 4px; }
        .invoice-meta { text-align: right; font-size: 0.9rem; color: #cbd5e1; }
        .invoice-meta strong { color: #7dd3fc; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
        .panel { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 14px 16px; }
        .panel h2 { margin: 0 0 8px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
        .panel p { margin: 2px 0; font-size: 0.92rem; color: #e5e7eb; line-height: 1.5; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th, table.items td { padding: 11px 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 0.92rem; }
        table.items th { color: #94a3b8; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        table.items td.num, table.items th.num { text-align: right; }
        .totals { display: flex; justify-content: flex-end; }
        .totals-box { width: 100%; max-width: 320px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; color: #cbd5e1; font-size: 0.95rem; }
        .totals-row.grand { border-top: 1px solid #26304a; margin-top: 6px; padding-top: 12px; font-weight: 800; font-size: 1.15rem; color: #fff; }
        .totals-row.discount span:last-child { color: #34d399; }
        .footer-note { margin-top: 24px; padding-top: 14px; border-top: 1px solid #26304a; color: #94a3b8; font-size: 0.82rem; text-align: center; }

        @media print {
            body { background: #ffffff; color: #111827; padding: 0; }
            .toolbar { display: none; }
            .invoice { border: none; border-radius: 0; background: #ffffff; color: #111827; box-shadow: none; max-width: none; }
            .invoice-header h1, .invoice-meta strong, .totals-row.grand { color: #111827; }
            .invoice-header, .footer-note { border-color: #d1d5db; }
            .panel { background: #f9fafb; border-color: #e5e7eb; }
            .panel h2, .invoice-header .store, .invoice-meta, table.items th { color: #4b5563; }
            .panel p, table.items td, .totals-row, .invoice-meta strong { color: #111827; }
            table.items th, table.items td { border-color: #e5e7eb; }
            .totals-row.discount span:last-child { color: #047857; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="btn-back" href="{{ $order->user_id ? route('admin.customers.show', $order->user_id) : route('admin.customers.index') }}">← Back to customer</a>
        <button class="btn-print" type="button" onclick="window.print()">Print invoice</button>
    </div>

    <div class="invoice">
        <div class="invoice-header">
            <div>
                <h1>KDP MART</h1>
                <div class="store">Open-source e-commerce store &middot; Invoice</div>
            </div>
            <div class="invoice-meta">
                <div>Invoice for order <strong>#{{ $order->order_number }}</strong></div>
                <div>Date: {{ $order->created_at->format('M d, Y') }}</div>
                <div>Payment: {{ ucfirst($order->payment_method ?? 'cod') }}</div>
                <div>Status: {{ $order->statusLabel() }}</div>
            </div>
        </div>

        <div class="grid">
            <div class="panel">
                <h2>Billed to</h2>
                <p><strong>{{ $order->user?->name ?? $order->shipping_name }}</strong></p>
                <p>{{ $order->user?->email ?? $order->customer_email }}</p>
                @if ($order->user?->phone)
                    <p>{{ $order->user->phone }}</p>
                @endif
            </div>
            <div class="panel">
                <h2>Ship to</h2>
                <p>{{ $order->shipping_name }}</p>
                <p>{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}{{ $order->shipping_country ? ', ' . $order->shipping_country : '' }}</p>
                <p>Phone: {{ $order->shipping_phone }}</p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_title }}
                            @if ($item->options_text)
                                <div style="color:#94a3b8; font-size:0.8rem;">{{ $item->options_text }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">&#8377;{{ number_format((float) $item->price, 2) }}</td>
                        <td class="num">&#8377;{{ number_format((float) $item->price * (int) $item->quantity, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8;">No items recorded for this order.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="totals-row"><span>Subtotal</span><span>&#8377;{{ number_format((float) $order->subtotal, 2) }}</span></div>
                @if ((float) $order->discount_amount > 0)
                    <div class="totals-row discount"><span>Coupon {{ $order->coupon_code ? '(' . $order->coupon_code . ')' : '' }}</span><span>&minus;&#8377;{{ number_format((float) $order->discount_amount, 2) }}</span></div>
                @endif
                <div class="totals-row"><span>Shipping ({{ $order->shipping_method ?? 'standard' }})</span><span>&#8377;{{ number_format((float) $order->shipping_cost, 2) }}</span></div>
                <div class="totals-row"><span>Tax</span><span>&#8377;{{ number_format((float) $order->tax, 2) }}</span></div>
                <div class="totals-row grand"><span>Total</span><span>&#8377;{{ number_format((float) $order->total, 2) }}</span></div>
            </div>
        </div>

        <div class="footer-note">
            Thank you for shopping with KDP MART. This invoice was generated for order #{{ $order->order_number }} on {{ now()->format('M d, Y') }}.
        </div>
    </div>
</body>
</html>