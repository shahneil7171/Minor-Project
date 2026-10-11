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
                <p><strong style="color:#fff;">Unit price paid:</strong> @money($breakdown['unit_price'])</p>
                <p><strong style="color:#fff;">&times; {{ $breakdown['quantity'] }} unit(s):</strong> @money($breakdown['gross'])</p>
                <p><strong style="color:#fff;">Coupon discount share:</strong> &#8722;@money($breakdown['discount'])</p>
                <p><strong style="color:#fff;">Tax share:</strong> +@money($breakdown['tax'])</p>
                <p><strong style="color:#fff;">Shipping refund:</strong> @money($breakdown['shipping'])</p>
                <p><strong style="color:#fff;">Total refund:</strong> @money($breakdown['total'])</p>
            @else
                <p style="color:var(--ka-muted);">The original order line is unavailable; stored refund amount @money((float) $returnRequest->refund_amount) is shown.</p>
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
        <h3>Returned item inspection &amp; inventory</h3>
        <p style="color:var(--ka-muted); margin:0 0 10px; font-size:.86rem;">
            A returned product is only put back into sellable stock once it has been
            <strong style="color:#fff;">received</strong> AND confirmed
            <strong style="color:#fff;">resellable</strong>. Damaged or non-resellable
            items are recorded for the audit trail but never increase the stock a buyer
            can purchase.
        </p>

        @if ($returnRequest->inventory_condition)
            <p style="color:#fff; margin:0 0 6px;">
                Condition: <strong>{{ $returnRequest->inventoryConditionLabel() }}</strong>
            </p>
        @endif

        @if ($returnRequest->isRestocked())
            <p style="color:#6ee7b7; margin:0;">
                Restocked {{ $returnRequest->restocked_at?->format('d M Y H:i') }}
                ({{ $returnRequest->restocked_quantity }} unit(s) returned to sellable stock).
            </p>
        @elseif ($returnRequest->isInspectable())
            <form method="POST" action="{{ route('admin.returns.restock', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                @csrf
                <div class="field" style="margin-bottom:0; min-width:190px;">
                    <label for="inventory_condition">Product condition</label>
                    <select name="inventory_condition" id="inventory_condition" required>
                        @foreach (\App\Models\ReturnRequest::INVENTORY_CONDITION_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($returnRequest->inventory_condition === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; flex:1; min-width:200px;">
                    <label for="restock_note">Note (optional)</label>
                    <input type="text" name="note" id="restock_note" maxlength="500" placeholder="e.g. Box opened, unit works perfectly">
                </div>
                <button type="submit" class="btn green">Save inspection result</button>
            </form>
            <p style="color:var(--ka-muted); margin:8px 0 0; font-size:.78rem;">
                Choosing <strong>Resellable</strong> adds the returned units back to stock exactly once;
                choosing Damaged or Non-resellable leaves sellable stock untouched.
            </p>
        @else
            <p style="color:var(--ka-muted); margin:0;">Mark the return as received before inspecting it.</p>
        @endif

        @error('inventory_condition')<p style="color:#fca5a5; margin:8px 0 0;">{{ $message }}</p>@enderror
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
    @php
        // Refund method initiation is offered once the product is received /
        // inspected (money is only owed once the goods are back). Every amount
        // shown here is recomputed server-side — never trusted from the form.
        $existingRefund = \App\Models\Refund::where('return_request_id', $returnRequest->id)->first();
        $canInitiate = ! $existingRefund && in_array($st, ['received', 'inspected', 'refund_processing'], true);
        $initAmount = $returnRequest->orderItem
            ? \App\Support\ReturnPolicy::refundBreakdown($returnRequest->order, $returnRequest->orderItem, (int) $returnRequest->quantity)['total']
            : (float) $returnRequest->refund_amount;
        $existingReplacement = \App\Models\Replacement::where('return_request_id', $returnRequest->id)->first();
    @endphp
    <div class="card">
        <h3>Refund method</h3>
        @if ($existingRefund)
            <p><strong style="color:#fff;">Method:</strong> {{ ucwords(str_replace('_', ' ', $existingRefund->method)) }}
                <span class="badge {{ $existingRefund->status }}" style="margin-left:6px;">{{ ucfirst($existingRefund->status) }}</span></p>
            <p><strong style="color:#fff;">Amount:</strong> ₹{{ number_format((float) $existingRefund->amount, 2) }}</p>
            @if ($existingRefund->provider_reference)
                <p><strong style="color:#fff;">Reference:</strong> {{ $existingRefund->provider_reference }}</p>
            @endif
            @if ($existingRefund->method !== 'completed' && in_array($existingRefund->method, ['smart_coins', 'original_simulated'], true) && $existingRefund->status !== 'completed')
                <form method="POST" action="{{ route('admin.refunds.settle', $existingRefund) }}" style="margin-top:8px;">
                    @csrf
                    <input type="text" name="provider_reference" placeholder="Provider reference (optional)" style="padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                    <button type="submit" class="btn green">{{ $existingRefund->method === 'smart_coins' ? 'Credit Smart Coins' : 'Complete refund (simulated)' }}</button>
                </form>
            @endif
            @if ($existingRefund->method === 'cod_cash' && ! $existingRefund->payout)
                <form method="POST" action="{{ route('admin.refunds.assign-payout', $existingRefund) }}" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                    @csrf
                    <select name="delivery_partner_id" required style="flex:1; min-width:200px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                        <option value="">— Assign refund agent —</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }} ({{ $partner->email }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn">Assign cash payout</button>
                </form>
            @elseif ($existingRefund->method === 'cod_cash' && $existingRefund->payout)
                <p style="color:var(--ka-muted);">Cash payout {{ $existingRefund->payout->deliveryPartner ? 'assigned to ' . $existingRefund->payout->deliveryPartner->name : 'pending assignment' }} · status: {{ ucfirst($existingRefund->payout->status) }}.</p>
            @endif
        @elseif ($canInitiate)
            <p style="color:var(--ka-muted);">Calculated refund: <strong style="color:#fff;">₹{{ number_format((float) $initAmount, 2) }}</strong>. Choose how to settle it.</p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="POST" action="{{ route('admin.returns.refund-initiate', $returnRequest) }}">@csrf<input type="hidden" name="method" value="original_simulated"><button type="submit" class="btn">Refund to original payment (simulated)</button></form>
                <form method="POST" action="{{ route('admin.returns.refund-initiate', $returnRequest) }}">@csrf<input type="hidden" name="method" value="smart_coins"><button type="submit" class="btn green">Refund as Smart Coins</button></form>
                <form method="POST" action="{{ route('admin.returns.refund-initiate', $returnRequest) }}">@csrf<input type="hidden" name="method" value="cod_cash"><button type="submit" class="btn gray">COD cash refund</button></form>
            </div>
        @else
            <p style="color:var(--ka-muted); margin:0;">A refund can be initiated once the returned product has been received and inspected.</p>
        @endif

        @if ($existingReplacement)
            <hr style="border-color:#374151;">
            <h3>Replacement</h3>
            <p><strong style="color:#fff;">Status:</strong> {{ ucfirst($existingReplacement->status) }}
                @if ((float) $existingReplacement->price_difference != 0.0)
                    · <strong style="color:#fff;">Difference:</strong> ₹{{ number_format((float) $existingReplacement->price_difference, 2) }} ({{ (float) $existingReplacement->price_difference > 0 ? 'customer owes' : 'refund difference' }})
                @endif
            </p>
            @if ($existingReplacement->status === 'requested')
                <form method="POST" action="{{ route('admin.returns.replacement-approve', $returnRequest) }}">@csrf<button type="submit" class="btn">Approve replacement</button></form>
            @endif
        @endif
    </div>
@endsection
