<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns &amp; Refunds | KDP MART</title>
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
        .badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; }
        .badge.pending { background: rgba(245,158,11,0.16); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .badge.approved { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.pickup_scheduled, .badge.picked_up { background: rgba(139,92,246,0.16); color: #c4b5fd; border: 1px solid rgba(139,92,246,0.4); }
        .badge.received, .badge.inspected { background: rgba(56,189,248,0.16); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.4); }
        .badge.refund_processing { background: rgba(99,102,241,0.18); color: #c7d2fe; border: 1px solid rgba(99,102,241,0.5); }
        .badge.refunded { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.rejected, .badge.cancelled { background: rgba(239,68,68,0.16); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
        .badge.refund_pending { background: rgba(245,158,11,0.16); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .rtable { width: 100%; border-collapse: collapse; }
        .rtable th { text-align: left; font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; color: #94a3b8; padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.14); }
        .rtable td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 0.88rem; color: #e2e8f0; vertical-align: top; }
        .rtable tr:last-child td { border-bottom: none; }
        .strong { color: #fff; font-weight: 700; }
        .muted { color: #94a3b8; font-size: 0.78rem; }
        .policy { border-radius: 14px; padding: 16px 18px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.35); }
        .policy h3 { margin: 0 0 8px; font-size: 0.95rem; color: #fcd34d; }
        .policy ul { margin: 0; padding-left: 18px; color: #cbd5e1; font-size: 0.85rem; line-height: 1.8; }
        .view-btn { display: inline-block; padding: 8px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.8rem; color: #bfdbfe; background: rgba(37,99,235,0.18); border: 1px solid rgba(37,99,235,0.5); }
        .view-btn:hover { background: rgba(37,99,235,0.34); color: #fff; }
        .empty { text-align: center; color: #94a3b8; padding: 30px 0; }
        @media (max-width: 760px) { .rtable thead { display: none; } .rtable tr { display: block; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); } .rtable td { display: block; border: none; padding: 3px 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <p style="margin:0; color:#94a3b8;">My Account</p>
                <h1 style="margin:4px 0 0;">Returns &amp; Refunds</h1>
            </div>
            <a href="{{ route('orders.index') }}">Back to my orders</a>
        </div>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        <div class="card policy">
            <h3>Return Policy</h3>
            <ul>
                @foreach ($policy as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
        <div class="card">
            <h2>Return requests</h2>
            @if ($returns->isEmpty())
                <div class="empty">You have not requested any returns yet. Open a delivered order in My Orders to start a return while the return window is open.</div>
            @else
                <div style="overflow-x:auto;">
                    <table class="rtable">
                        <thead>
                            <tr>
                                <th>Return</th><th>Order</th><th>Product</th><th>Delivered / Deadline</th><th>Reason</th><th>Status</th><th>Refund</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($returns as $return)
                                <tr>
                                    <td>
                                        <div class="strong">{{ $return->return_number }}</div>
                                        <div class="muted">Requested {{ $return->created_at->format('M d, Y') }}</div>
                                    </td>
                                    <td>
                                        <div class="strong">#{{ $return->order_number }}</div>
                                        <div class="muted">Placed {{ optional($return->order)->created_at?->format('M d, Y') ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div class="strong">{{ $return->product_title }}</div>
                                        <div class="muted">Qty: {{ $return->quantity }}</div>
                                    </td>
                                    <td>
                                        <div>{{ optional($return->order)->deliveredAt()?->format('M d, Y') ?? '—' }}</div>
                                        <div class="muted">Return until {{ optional($return->return_deadline)->format('M d, Y') }}</div>
                                    </td>
                                    <td>
                                        {{ $return->reason }}
                                        @if ($return->isRejected() && $return->rejection_reason)
                                            <div class="muted" style="color:#fca5a5;">{{ $return->rejection_reason }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $return->status }}">{{ $return->statusLabel() }}</span></td>
                                    <td>
                                        <span class="badge {{ $return->refund_status === 'refunded' ? 'refunded' : ($return->refund_status === 'processing' ? 'refund_processing' : ($return->refund_status === 'pending' ? 'refund_pending' : 'pending')) }}">{{ $return->refundStatusLabel() }}</span>
                                        @if ((float) $return->refund_amount > 0)
                                            <div class="muted">&#8377;{{ number_format((float) $return->refund_amount, 2) }}</div>
                                        @endif
                                    </td>
                                    <td><a class="view-btn" href="{{ route('returns.show', ['return' => $return]) }}">View Details</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($returns->hasPages())
            <div style="text-align:center; color:#94a3b8;">{{ $returns->links() }}</div>
        @endif

    </div>
</body>
</html>
