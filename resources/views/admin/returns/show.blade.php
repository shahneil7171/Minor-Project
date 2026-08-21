@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Return #' . $returnRequest->id)

@section('content')
    <div class="page-head">
        <h2>Return #{{ $returnRequest->id }}
            <span class="badge {{ $returnRequest->status }}" style="vertical-align:middle; margin-left:8px;">{{ \App\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] }}</span>
        </h2>
        <a class="btn gray" href="{{ route('admin.returns.index') }}">← Back</a>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Return details</h3>
            <p><strong style="color:#fff;">Product:</strong> {{ $returnRequest->product_title }}</p>
            @if ($returnRequest->product_slug)
                <p><strong style="color:#fff;">Slug:</strong> {{ $returnRequest->product_slug }}</p>
            @endif
            <p><strong style="color:#fff;">Reason:</strong></p>
            <p style="color:var(--ka-muted);">{{ $returnRequest->reason }}</p>
            @if ($returnRequest->admin_note)
                <p><strong style="color:#fff;">Admin note:</strong> {{ $returnRequest->admin_note }}</p>
            @endif
            <p style="color:var(--ka-muted);">Created {{ $returnRequest->created_at->format('M d, Y h:i A') }}</p>
        </div>

        <div class="card">
            <h3>Order &amp; customer</h3>
            <p><strong style="color:#fff;">Order:</strong>
                @if ($returnRequest->order)
                    <a href="{{ route('orders.show', $returnRequest->order) }}" style="color:#93c5fd;">#{{ $returnRequest->order_number }}</a>
                    ({{ \App\Models\Order::STATUS_LABELS[$returnRequest->order->status] ?? $returnRequest->order->status }})
                @else
                    —
                @endif
            </p>
            <p><strong style="color:#fff;">Customer:</strong>
                @if ($returnRequest->customer)
                    <a href="{{ route('admin.customers.show', $returnRequest->customer) }}" style="color:#93c5fd;">{{ $returnRequest->customer->name }}</a>
                @elseif ($returnRequest->customer_email)
                    {{ $returnRequest->customer_email }}
                @else
                    —
                @endif
            </p>
            @if ($returnRequest->order && $returnRequest->order->items->isNotEmpty())
                <p><strong style="color:#fff;">Order items:</strong></p>
                <ul style="color:var(--ka-muted); margin:4px 0 0; padding-left:18px;">
                    @foreach ($returnRequest->order->items as $item)
                        <li>{{ $item->product_title }} × {{ $item->quantity }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="card" style="max-width:560px;">
        <h3>Update status</h3>
        <form method="POST" action="{{ route('admin.returns.status', $returnRequest) }}" style="display:flex; gap:10px; flex-wrap:wrap;">
            @csrf
            <select name="status" style="flex:1; min-width:180px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                @foreach (\App\Models\ReturnRequest::STATUSES as $s)
                    <option value="{{ $s }}" {{ $returnRequest->status === $s ? 'selected' : '' }}>{{ \App\Models\ReturnRequest::STATUS_LABELS[$s] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn">Update Status</button>
        </form>
    </div>
@endsection
