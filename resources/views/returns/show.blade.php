<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $returnRequest->return_number }} | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 860px; margin: 0 auto; border-radius: 24px; padding: 26px; background: rgba(2,6,23,0.85); border: 1px solid rgba(255,255,255,0.16); box-shadow: 0 24px 50px rgba(0,0,0,0.28); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 6px; font-size: 1.6rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .card { border-radius: 18px; padding: 20px 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 18px; }
        .card h2 { margin: 0 0 14px; font-size: 1.05rem; color: #fff; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 800; }
        .badge.pending { background: rgba(245,158,11,0.16); color: #fcd34d; border: 1px solid rgba(245,158,11,0.4); }
        .badge.approved { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.pickup_scheduled, .badge.picked_up { background: rgba(139,92,246,0.16); color: #c4b5fd; border: 1px solid rgba(139,92,246,0.4); }
        .badge.received, .badge.inspected { background: rgba(56,189,248,0.16); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.4); }
        .badge.refund_processing { background: rgba(99,102,241,0.18); color: #c7d2fe; border: 1px solid rgba(99,102,241,0.5); }
        .badge.refunded { background: rgba(16,185,129,0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.4); }
        .badge.rejected, .badge.cancelled { background: rgba(239,68,68,0.16); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
        .steps { list-style: none; margin: 14px 0 0; padding: 0; }
        .steps li { display: flex; gap: 10px; align-items: baseline; padding: 7px 0; color: #64748b; font-size: 0.9rem; }
        .steps li .dot { width: 13px; height: 13px; border-radius: 999px; border: 2px solid #475569; background: #0f172a; flex: none; align-self: center; }
        .steps li.done { color: #6ee7b7; }
        .steps li.done .dot { background: #10b981; border-color: #10b981; }
        .steps li.current { color: #7dd3fc; font-weight: 700; }
        .steps li.current .dot { background: #2563eb; border-color: #38bdf8; box-shadow: 0 0 0 4px rgba(56,189,248,0.15); }
        .steps .when { margin-left: auto; color: #64748b; font-size: 0.78rem; font-weight: 400; }
        .kv { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; color: #cbd5e1; font-size: 0.9rem; }
        .kv span:first-child { color: #94a3b8; flex: none; }
        .kv span:last-child { text-align: right; }
        .kv.grand { border-top: 1px solid rgba(255,255,255,0.16); margin-top: 6px; padding-top: 10px; color: #fff; font-weight: 800; }
        .note { padding: 14px 16px; border-radius: 12px; background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.4); color: #fecaca; font-weight: 600; }
        .evidence { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .evidence img { width: 84px; height: 84px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(255,255,255,0.14); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <p style="margin:0; color:#94a3b8;">Return request</p>
                <h1 style="margin:4px 0 0;">{{ $returnRequest->return_number }} <span class="badge {{ $returnRequest->status }}" style="vertical-align:middle;">{{ $returnRequest->statusLabel() }}</span></h1>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="{{ route('returns.index') }}">All returns</a>
                <a href="{{ $returnRequest->order ? route('orders.show', ['order' => $returnRequest->order]) : route('orders.index') }}">View order</a>
            </div>
        </div>
        @if (session('success'))
            <div class="card" style="background:rgba(56,189,248,0.12); border-color:rgba(56,189,248,0.4);">{{ session('success') }}</div>
        @endif

        @if ($returnRequest->isRejected())
            <div class="card">
                <h2>Return rejected</h2>
                <div class="note">{{ $returnRequest->rejection_reason ?: 'Your return request was rejected.' }}</div>
                <p style="color:#94a3b8; font-size:0.82rem; margin:10px 0 0;">
                    Rejected {{ optional($returnRequest->rejected_at)->format('M d, Y h:i A') }}.
                </p>
            </div>
        @endif

        <div class="card">
            <h2>Tracking</h2>
            @php
                $steps = [
                    ['Return Requested', $returnRequest->requested_at],
                    ['Approved', $returnRequest->approved_at],
                    ['Pickup Scheduled', $returnRequest->pickup_scheduled_at],
                    ['Product Received', $returnRequest->received_at],
                    ['Refund Processing', $returnRequest->refund_processing_at],
                    ['Refunded', $returnRequest->refunded_at],
                ];
                $current = match ($returnRequest->status) {
                    'pending' => 0,
                    'approved' => 1,
                    'pickup_scheduled', 'picked_up' => 2,
                    'received', 'inspected' => 3,
                    'refund_processing' => 4,
                    'refunded' => 5,
                    default => -1,
                };
            @endphp
            <ul class="steps">
                @foreach ($steps as $i => [$label, $when])
                    <li class="{{ $i < $current || ($i === $current && $current === 5) ? 'done' : ($i === $current ? 'current' : '') }}">
                        <span class="dot"></span>
                        <span>{{ $label }}</span>
                        @if ($when)
                            <span class="when">{{ $when->format('M d, Y h:i A') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card">
            <h2>Return details</h2>
            <div class="kv"><span>Product</span><span>{{ $returnRequest->product_title }}</span></div>
            <div class="kv"><span>Quantity</span><span>{{ $returnRequest->quantity }}</span></div>
            <div class="kv"><span>Order</span><span>#{{ $returnRequest->order_number }}</span></div>
            <div class="kv"><span>Requested on</span><span>{{ optional($returnRequest->requested_at, fn ($d) => $d->format('M d, Y h:i A')) ?? $returnRequest->created_at->format('M d, Y h:i A') }}</span></div>
            @if ($returnRequest->order)
                <div class="kv"><span>Purchased on</span><span>{{ $returnRequest->order->created_at->format('M d, Y') }}</span></div>
                <div class="kv"><span>Delivered on</span><span>{{ optional($returnRequest->order->deliveredAt(), fn ($d) => $d->format('M d, Y')) ?? '—' }}</span></div>
            @endif
            <div class="kv"><span>Return deadline</span><span>{{ optional($returnRequest->return_deadline, fn ($d) => $d->format('M d, Y')) ?? '—' }}</span></div>
            <div class="kv"><span>Reason</span><span>{{ $returnRequest->reason }}</span></div>
            @if ($returnRequest->description)
                <div class="kv"><span>Your description</span><span>{{ $returnRequest->description }}</span></div>
            @endif
            @if ($returnRequest->seller)
                <div class="kv"><span>Seller</span><span>{{ $returnRequest->seller->name }}</span></div>
            @endif
            @if ($returnRequest->deliveryPartner)
                <div class="kv"><span>Pickup partner</span><span>{{ $returnRequest->deliveryPartner->name }}</span></div>
            @endif
            @if ($returnRequest->pickup_notes)
                <div class="kv"><span>Pickup instructions</span><span>{{ $returnRequest->pickup_notes }}</span></div>
            @endif
            @if ($returnRequest->admin_note)
                <div class="kv"><span>Admin note</span><span>{{ $returnRequest->admin_note }}</span></div>
            @endif
            @if ($returnRequest->images)
                <div style="margin-top:10px;">
                    <div style="color:#94a3b8; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em;">Evidence images</div>
                    <div class="evidence">
                        @foreach ($returnRequest->images as $image)
                            <img src="{{ asset('storage/' . $image) }}" alt="Return evidence">
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Refund</h2>
            <div class="kv"><span>Refund status</span><span><span class="badge {{ $returnRequest->refund_status === 'refunded' ? 'refunded' : ($returnRequest->refund_status === 'processing' ? 'refund_processing' : 'pending') }}">{{ $returnRequest->refundStatusLabel() }}</span></span></div>
            @if ($returnRequest->orderItem)
                @php $b = \App\Support\ReturnPolicy::refundBreakdown($returnRequest->order, $returnRequest->orderItem, (int) $returnRequest->quantity); @endphp
                <div class="kv"><span>Product amount</span><span>&#8377;{{ number_format($b['product'], 2) }}</span></div>
                <div class="kv"><span>Shipping refund</span><span>&#8377;{{ number_format($b['shipping'], 2) }}</span></div>
                <div class="kv grand"><span>Total refund</span><span>&#8377;{{ number_format($b['total'], 2) }}</span></div>
            @elseif ((float) $returnRequest->refund_amount > 0)
                <div class="kv grand"><span>Total refund</span><span>&#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</span></div>
            @endif
            @if ($returnRequest->refund_reference)
                <div class="kv"><span>Reference</span><span>{{ $returnRequest->refund_reference }}</span></div>
            @endif
            @if ($returnRequest->refunded_at)
                <div class="kv"><span>Refunded on</span><span>{{ $returnRequest->refunded_at->format('M d, Y h:i A') }}</span></div>
            @endif
        </div>

    </div>
</body>
</html>
