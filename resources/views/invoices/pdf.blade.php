<!-- PHASE 6 — printable invoice / receipt rendered to PDF by InvoicePdfService.
     dompdf-safe on purpose: TABLES only (no flex/grid), no external assets,
     DejaVu Sans so the rupee currency glyph embeds correctly. Every amount
     goes through the orderMoney directive so the order's checkout-time
     currency snapshot is what the document shows, forever. -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} #{{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #111827; margin: 0; }
        .sheet { padding: 28px 30px; }
        .header-table { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 14px; margin-bottom: 16px; }
        .header-table td { vertical-align: top; }
        .store-name { font-size: 20px; font-weight: bold; margin: 0; }
        .store-line { font-size: 11px; color: #4b5563; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title .label { font-size: 18px; font-weight: bold; letter-spacing: 0.04em; }
        .doc-title .meta { font-size: 11px; color: #374151; margin-top: 4px; }
        .banner { padding: 8px 12px; margin-bottom: 16px; text-align: center; font-weight: bold; letter-spacing: 0.06em; }
        .banner.paid { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .banner.pending { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
        .banner.due { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .banner.failed { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .panel-table { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .panel-table td { width: 50%; vertical-align: top; padding: 10px 14px; border: 1px solid #e5e7eb; }
        .panel-table h2 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; }
        .panel-table p { margin: 1px 0; font-size: 11px; line-height: 1.5; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #d1d5db; padding: 7px 8px; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        .item-opt { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .empty-row { text-align: center; color: #6b7280; padding: 14px 8px; }
        table.totals { width: 46%; margin-left: 54%; border-collapse: collapse; margin-bottom: 18px; }
        table.totals td { padding: 4px 8px; font-size: 11px; }
        table.totals .lbl { text-align: left; color: #374151; }
        table.totals .amt { text-align: right; white-space: nowrap; }
        table.totals tr.discount .lbl, table.totals tr.discount .amt { color: #047857; }
        table.totals tr.grand td { border-top: 2px solid #111827; padding-top: 8px; font-weight: bold; font-size: 13px; }
        .pay-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .pay-table td { padding: 4px 8px; font-size: 11px; border-bottom: 1px solid #f3f4f6; }
        .pay-table .k { width: 34%; color: #6b7280; }
        .refunds h2 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        table.refund-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.refund-table th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 6px 8px; }
        table.refund-table td { padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #f3f4f6; }
        table.refund-table .num { text-align: right; white-space: nowrap; }
        .footer-note { margin-top: 20px; padding-top: 10px; border-top: 1px solid #d1d5db; font-size: 10px; color: #6b7280; text-align: center; line-height: 1.6; }
    </style>
    <table class="header-table">
        <tr>
            <td>
                <p class="store-name">{{ $store['name'] }}</p>
                <p class="store-line">{{ $store['email'] }}{{ $store['phone'] ? ' &middot; ' . $store['phone'] : '' }}</p>
            </td>
            <td class="doc-title">
                <div class="label">{{ strtoupper($title) }}</div>
                <div class="meta">
                    #{{ $order->order_number }}<br>
                    Issued {{ $generatedAt->format('M d, Y') }} &middot; Order placed {{ $order->created_at->format('M d, Y') }}<br>
                    Order status: {{ $order->statusLabel() }}
                </div>
            </td>
        </tr>
    </table>

    @if ($type === 'receipt' && $payment)
        <div class="banner {{ $payment['state'] }}">{{ $payment['banner'] }}</div>
    @endif

    <table class="panel-table">
        <tr>
            <td>
                <h2>Billed to</h2>
                <p><strong>{{ $order->user?->name ?? $order->shipping_name ?? '—' }}</strong></p>
                <p>{{ $order->user?->email ?? $order->customer_email }}</p>
                @if ($order->user?->phone)
                    <p>{{ $order->user->phone }}</p>
                @endif
            </td>
            <td>
                <h2>Ship to</h2>
                <p><strong>{{ $order->shipping_name ?? '—' }}</strong></p>
                <p>
                    {{ $order->shipping_address ?? '' }}{{ $order->shipping_city ? ', ' . $order->shipping_city : '' }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }}{{ $order->shipping_pincode ? ' ' . $order->shipping_pincode : '' }}{{ $order->shipping_country ? ', ' . $order->shipping_country : '' }}
                </p>
                @if ($order->shipping_phone)
                    <p>Phone: {{ $order->shipping_phone }}</p>
                @endif
            </td>
        </tr>
    </table>

    @if ($scopedSeller)
        <p style="font-size:10px; color:#6b7280; margin:0 0 8px;">
            Statement of your products included in order #{{ $order->order_number }}.
        </p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>
                        {{ $item->product_title }}
                        @if ($item->options_text)
                            <div class="item-opt">{{ $item->options_text }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">@orderMoney($order, (float) $item->price)</td>
                    <td class="num">@orderMoney($order, (float) $item->price * (int) $item->quantity)</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-row">No items recorded for this order.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</head>
<body>
<div class="sheet">

    @if ($scopedSeller)
        <table class="totals">
            <tr>
                <td class="lbl">Subtotal ({{ $items->count() }} line{{ $items->count() === 1 ? '' : 's' }})</td>
                <td class="amt">@orderMoney($order, $items->sum(fn ($i) => (float) $i->price * (int) $i->quantity))</td>
            </tr>
            <tr class="grand">
                <td class="lbl">Total for your products</td>
                <td class="amt">@orderMoney($order, $items->sum(fn ($i) => (float) $i->price * (int) $i->quantity))</td>
            </tr>
        </table>
    @else
        <table class="totals">
            <tr>
                <td class="lbl">Subtotal</td>
                <td class="amt">@orderMoney($order, (float) $order->subtotal)</td>
            </tr>
            @if ((float) $order->discount_amount > 0)
                <tr class="discount">
                    <td class="lbl">Coupon discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}</td>
                    <td class="amt">-@orderMoney($order, (float) $order->discount_amount)</td>
                </tr>
            @endif
            <tr>
                <td class="lbl">Shipping ({{ $order->shipping_method ?? 'standard' }})</td>
                <td class="amt">@orderMoney($order, (float) $order->shipping_cost)</td>
            </tr>
            <tr>
                <td class="lbl">Tax</td>
                <td class="amt">@orderMoney($order, (float) $order->tax)</td>
            </tr>
            @if ((float) $order->coins_redeemed > 0)
                <tr>
                    <td class="lbl">Original total</td>
                    <td class="amt">@orderMoney($order, $order->originalTotal())</td>
                </tr>
                <tr class="discount">
                    <td class="lbl">Smart Coins redeemed ({{ rtrim(rtrim(number_format((float) $order->coins_redeemed, 2), '0'), '.') }} coins)</td>
                    <td class="amt">-@orderMoney($order, $order->coinsValue())</td>
                </tr>
            @endif
            <tr class="grand">
                <td class="lbl">{{ $grandPaid ? 'Amount paid' : 'Amount due' }}</td>
                <td class="amt">@orderMoney($order, $order->amountDue())</td>
            </tr>
        </table>

        @if ($payment)
            <table class="pay-table">
                <tr><td class="k">Payment method</td><td>{{ $payment['method'] }}</td></tr>
                <tr><td class="k">Payment status</td><td>{{ $payment['label'] }}</td></tr>
                @if ($payment['reference'])
                    <tr><td class="k">Payment reference</td><td>{{ $payment['reference'] }}</td></tr>
                @endif
            </table>
        @endif

        @if ($refunds->isNotEmpty())
            <div class="refunds">
                <h2>Refunds</h2>
                <table class="refund-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refunds as $refund)
                            <tr>
                                <td>{{ ($refund->completed_at ?? $refund->created_at)->format('M d, Y') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', (string) $refund->method)) }}</td>
                                <td>{{ ucfirst((string) $refund->status) }}</td>
                                <td class="num">@orderMoney($order, (float) $refund->amount)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <div class="footer-note">
        {{ $title }} #{{ $order->order_number }} &middot; generated {{ $generatedAt->format('M d, Y h:i A') }}<br>
        This is a computer-generated document and is valid without a signature.<br>
        For any queries contact {{ $store['name'] }} at {{ $store['email'] }}.
    </div>

</div>
</body>
</html>
