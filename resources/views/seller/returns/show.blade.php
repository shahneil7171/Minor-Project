@extends('layouts.app')

@section('title', 'Return ' . $returnRequest->return_number)

@section('content')
<div class="container-fluid" style="max-width:1000px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-rotate-left me-2" style="color:var(--primary-color);"></i>{{ $returnRequest->return_number }}</h1>
            <p class="text-muted mb-0">Return for Order #{{ $returnRequest->order_number }}</p>
        </div>
        <a href="{{ route('seller.returns.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> All returns</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Product</h5>
                    <p class="mb-1"><strong>{{ $returnRequest->product_title }}</strong></p>
                    <p class="text-muted mb-1">Quantity: {{ $returnRequest->quantity }}</p>
                    <p class="text-muted mb-0">Order: #{{ $returnRequest->order_number }} ({{ \App\Models\Order::STATUS_LABELS[$returnRequest->order->status] ?? $returnRequest->order?->status }})</p>
                    <hr>
                    <p class="mb-1"><strong>Reason:</strong> {{ $returnRequest->reason }}</p>
                    @if ($returnRequest->description)
                        <p class="text-muted mb-0">{{ $returnRequest->description }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Buyer &amp; status</h5>
                    <p class="mb-1"><strong>{{ $returnRequest->customer?->name ?? $returnRequest->customer_email }}</strong></p>
                    <p class="text-muted mb-1">Requested {{ optional($returnRequest->requested_at, fn ($d) => $d->format('M d, Y h:i A')) ?? $returnRequest->created_at->format('M d, Y h:i A') }}</p>
                    <p class="mb-1">Return status: <span class="badge" style="background:var(--primary-color);">{{ $returnRequest->statusLabel() }}</span></p>
                    <p class="mb-1">Refund status: <span class="badge bg-secondary">{{ $returnRequest->refundStatusLabel() }}</span></p>
                    @if ((float) $returnRequest->refund_amount > 0)
                        <p class="mb-0">Refund amount: <strong>&#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</strong></p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($returnRequest->images)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h5 class="card-title">Evidence images</h5>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach ($returnRequest->images as $image)
                        <img src="{{ asset('storage/' . $image) }}" alt="Return evidence" style="width:96px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #dee2e6;">
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body">
            <h5 class="card-title">Note</h5>
            <p class="text-muted mb-0">Acceptance, pickup and refund decisions are made by the KDP MART admin team. You will receive a notification when the status changes.</p>
        </div>
    </div>
</div>
@endsection
