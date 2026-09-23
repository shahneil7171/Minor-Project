@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Return ' . $returnRequest->return_number)

@section('content')
    <div class="page-head">
        <h2>{{ $returnRequest->return_number }}
            <span class="badge {{ $returnRequest->status }}" style="vertical-align:middle; margin-left:8px;">{{ $returnRequest->statusLabel() }}</span>
            <span class="badge {{ $returnRequest->refund_status }}" style="vertical-align:middle; margin-left:4px;">Refund: {{ $returnRequest->refundStatusLabel() }}</span>
        </h2>
        <a class="btn gray" href="{{ route('admin.returns.index') }}">← Back</a>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Return details</h3>
            <p><strong style="color:#fff;">Product:</strong> {{ $returnRequest->product_title }}</p>
            <p><strong style="color:#fff;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
            <p><strong style="color:#fff;">Reason:</strong></p>
            <p style="color:var(--ka-muted);">{{ $returnRequest->reason }}</p>
            @if ($returnRequest->description)
                <p><strong style="color:#fff;">Buyer description:</strong></p>
                <p style="color:var(--ka-muted);">{{ $returnRequest->description }}</p>
            @endif
            @if ($returnRequest->images)
                <p><strong style="color:#fff;">Evidence images:</strong></p>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 0;">
                    @foreach ($returnRequest->images as $image)
                        <a href="{{ asset('storage/' . $image) }}" target="_blank" rel="noopener">
                            <img src="{{ asset('storage/' . $image) }}" alt="Evidence" style="width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #374151;">
                        </a>
                    @endforeach
                </div>
            @endif
            <p style="color:var(--ka-muted); margin-top:10px;">Requested {{ optional($returnRequest->requested_at, fn ($d) => $d->format('M d, Y h:i A')) ?? $returnRequest->created_at->format('M d, Y h:i A') }}</p>
            @if ($returnRequest->return_deadline)
                <p style="color:var(--ka-muted); margin:0;">Return deadline {{ $returnRequest->return_deadline->format('M d, Y') }}</p>
            @endif
        </div>

        <div class="card">
            <h3>Order, buyer &amp; seller</h3>
            <p><strong style="color:#fff;">Order:</strong>
                @if ($returnRequest->order)
                    <a href="{{ route('orders.show', $returnRequest->order) }}" style="color:#93c5fd;">#{{ $returnRequest->order_number }}</a>
                    ({{ \App\Models\Order::STATUS_LABELS[$returnRequest->order->status] ?? $returnRequest->order->status }})
                @else
                    —
                @endif
            </p>
            <p><strong style="color:#fff;">Buyer:</strong>
                @if ($returnRequest->customer)
                    <a href="{{ route('admin.customers.show', $returnRequest->customer) }}" style="color:#93c5fd;">{{ $returnRequest->customer->name }}</a>
                @elseif ($returnRequest->customer_email)
                    {{ $returnRequest->customer_email }}
                @else
                    —
                @endif
            </p>
            <p><strong style="color:#fff;">Seller:</strong> {{ $returnRequest->seller?->name ?? '—' }}</p>
            @if ($returnRequest->deliveryPartner)
                <p><strong style="color:#fff;">Pickup partner:</strong> {{ $returnRequest->deliveryPartner->name }}
                    (assigned {{ optional($returnRequest->pickup_scheduled_at, fn ($d) => $d->format('M d, Y')) }})</p>
            @endif
            @if ($returnRequest->pickup_notes)
                <p><strong style="color:#fff;">Pickup instructions:</strong> {{ $returnRequest->pickup_notes }}</p>
            @endif
            @if ($returnRequest->rejection_reason)
                <p style="color:#fca5a5;"><strong>Rejection reason:</strong> {{ $returnRequest->rejection_reason }}</p>
            @endif
        </div>
    </div>
    <div class="grid-2">
        <div class="card">
            <h3>Refund breakdown (from recorded order data)</h3>
            @if ($breakdown)
                <p><strong style="color:#fff;">Unit price paid:</strong> &#8377;{{ number_format($breakdown['unit_price'], 2) }}</p>
                <p><strong style="color:#fff;">&times; {{ $breakdown['quantity'] }} unit(s):</strong> &#8377;{{ number_format($breakdown['gross'], 2) }}</p>
                <p><strong style="color:#fff;">Coupon discount share:</strong> &#8722;&#8377;{{ number_format($breakdown['discount'], 2) }}</p>
                <p><strong style="color:#fff;">Tax share:</strong> +&#8377;{{ number_format($breakdown['tax'], 2) }}</p>
                <p><strong style="color:#fff;">Shipping refund:</strong> &#8377;{{ number_format($breakdown['shipping'], 2) }}</p>
                <p><strong style="color:#fff;">Total refund:</strong> &#8377;{{ number_format($breakdown['total'], 2) }}</p>
            @else
                <p style="color:var(--ka-muted);">The original order line is unavailable; stored refund amount &#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }} is shown.</p>
            @endif
            @if ($returnRequest->refund_reference)
                <p><strong style="color:#fff;">Refund reference:</strong> {{ $returnRequest->refund_reference }}</p>
            @endif
            @if ($returnRequest->refunded_at)
                <p><strong style="color:#fff;">Refunded at:</strong> {{ $returnRequest->refunded_at->format('M d, Y h:i A') }}</p>
            @endif
            <p style="color:var(--ka-muted); margin:0;">Refund tracking only — no payment-gateway refund API is integrated in this demo.</p>
        </div>

        <div class="card">
            <h3>Status history</h3>
            <p style="color:var(--ka-muted); margin:0 0 8px;">
                Requested {{ optional($returnRequest->requested_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }} ·
                Approved {{ optional($returnRequest->approved_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }} ·
                Pickup {{ optional($returnRequest->pickup_scheduled_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }} ·
                Received {{ optional($returnRequest->received_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }} ·
                Refunded {{ optional($returnRequest->refunded_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }}
            </p>
            @if ($returnRequest->admin_note)
                <p><strong style="color:#fff;">Admin note:</strong> {{ $returnRequest->admin_note }}</p>
            @endif
        </div>
    </div>
    <div class="card">
        <h3>Workflow actions</h3>
        @php $st = $returnRequest->status; @endphp

        @if ($st === 'pending')
            <form method="POST" action="{{ route('admin.returns.approve', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
                @csrf
                <input type="text" name="admin_note" placeholder="Optional note to buyer/seller" style="flex:1; min-width:220px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <button type="submit" class="btn">Approve Return</button>
            </form>
            <form method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap;">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Rejection reason (required)" required minlength="5" style="flex:1; min-width:220px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <button type="submit" class="btn red">Reject Return</button>
            </form>
        @elseif ($st === 'approved')
            <form method="POST" action="{{ route('admin.returns.pickup', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
                @csrf
                <select name="delivery_partner_id" required style="flex:1; min-width:200px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                    <option value="">— Select delivery partner —</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }} ({{ $partner->email }})</option>
                    @endforeach
                </select>
                <input type="text" name="pickup_instructions" placeholder="Pickup instructions (optional)" style="flex:1; min-width:200px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <button type="submit" class="btn">Schedule Pickup</button>
            </form>
            <form method="POST" action="{{ route('admin.returns.received', $returnRequest) }}">
                @csrf
                <button type="submit" class="btn gray">Mark Received (no pickup needed)</button>
            </form>
        @elseif (in_array($st, ['pickup_assigned', 'pickup_scheduled', 'picked_up'], true))
            <form method="POST" action="{{ route('admin.returns.received', $returnRequest) }}">
                @csrf
                <button type="submit" class="btn">Mark Received</button>
            </form>
        @elseif (in_array($st, ['received', 'inspected'], true))
            <form method="POST" action="{{ route('admin.returns.refund-start', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap;">
                @csrf
                <input type="text" name="refund_reference" placeholder="Refund reference (optional)" style="flex:1; min-width:220px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <button type="submit" class="btn">Start Refund</button>
            </form>
        @elseif ($st === 'refund_processing')
            <form method="POST" action="{{ route('admin.returns.refund-complete', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap;">
                @csrf
                <input type="text" name="refund_reference" placeholder="Refund reference (optional)" style="flex:1; min-width:220px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <button type="submit" class="btn">Mark Refunded</button>
            </form>
        @else
            <p style="color:var(--ka-muted); margin:0;">No actions available in the "{{ $returnRequest->statusLabel() }}" state.</p>
        @endif

        @error('rejection_reason')<p style="color:#fca5a5;">{{ $message }}</p>@enderror
        @error('delivery_partner_id')<p style="color:#fca5a5;">{{ $message }}</p>@enderror
        @error('status')<p style="color:#fca5a5;">{{ $message }}</p>@enderror
    </div>
@endsection
