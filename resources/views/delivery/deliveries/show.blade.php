@extends('layouts.app')

@section('title', 'Delivery — Order #' . $order->order_number)

@section('content')
<div class="container-fluid" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-box me-2" style="color:var(--primary-color);"></i>Delivery for Order #{{ $order->order_number }}</h1>
            <p class="text-muted mb-0">
                Order status: <span class="fw-bold">{{ $order->statusLabel() }}</span>
                <span class="mx-1">·</span>
                Delivery status: <span class="badge" style="background:var(--primary-color);">{{ $delivery->statusLabel() }}</span>
            </p>
        </div>
        <a href="{{ route('delivery.deliveries.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-4">
        {{-- Customer information (only what is needed for delivery) --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-user me-2" style="color:var(--primary-color);"></i>Customer Information</div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $order->shipping_name }}</strong></p>
                    <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i>{{ $order->shipping_phone }}</p>
                    <p class="mb-0 text-muted">
                        {{ $order->shipping_address }}<br>
                        {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}<br>
                        {{ $order->shipping_country }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Delivery information --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-truck-fast me-2" style="color:var(--primary-color);"></i>Delivery Information</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Status:</strong> {{ $delivery->statusLabel() }}</p>
                    <p class="mb-1 text-muted small">Assigned: {{ $delivery->assigned_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    <p class="mb-1 text-muted small">Picked up: {{ $delivery->picked_up_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    <p class="mb-1 text-muted small">Out for delivery: {{ $delivery->out_for_delivery_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    <p class="mb-1 text-muted small">Delivered: {{ $delivery->delivered_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    @if ($order->notes)
                        <p class="mb-1 small"><strong>Delivery instructions:</strong><br><span class="text-muted">{{ $order->notes }}</span></p>
                    @endif
                    @if ($delivery->delivery_notes)
                        <p class="mb-0 small"><strong>Notes:</strong><br><span class="text-muted">{{ $delivery->delivery_notes }}</span></p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Order information --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-cart-shopping me-2" style="color:var(--primary-color);"></i>Order Information</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Product</th><th>SKU</th><th>Variant</th><th>Seller / Pickup</th><th>Qty</th><th>Price</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($item->product_image)
                                            <img src="{{ $item->product_image }}" alt="{{ $item->product_title }}" style="width:42px; height:42px; object-fit:cover; border-radius:8px;">
                                        @endif
                                        <span>{{ $item->product_title }}</span>
                                    </div>
                                </td>
                                <td class="small text-muted">{{ $item->sku ?? '—' }}</td>
                                <td class="small text-muted">{{ $item->options_text ?? '—' }}</td>
                                <td class="small">{{ $item->seller?->name ?? 'KDP MART' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>&#8377;{{ number_format((float) $item->price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">Order placed {{ $order->created_at->format('M d, Y h:i A') }}</span>
                <span class="fw-bold">Order total: &#8377;{{ number_format((float) $order->total, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Controlled status actions — only the assigned partner can perform these (server-side check) --}}
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-list-check me-2" style="color:var(--primary-color);"></i>Delivery Actions</div>
        <div class="card-body">
            @if ($delivery->status === 'assigned' || $delivery->status === 'ready_for_pickup')
                <form method="POST" action="{{ route('delivery.deliveries.pickup', $delivery) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">
                        <i class="fas fa-hand-holding-box me-1"></i> Mark Picked Up
                    </button>
                </form>
            @endif

            @if ($delivery->status === 'picked_up')
                <form method="POST" action="{{ route('delivery.deliveries.out-for-delivery', $delivery) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">
                        <i class="fas fa-route me-1"></i> Mark Out for Delivery
                    </button>
                </form>
            @endif

            @if ($delivery->status === 'out_for_delivery')
                <form method="POST" action="{{ route('delivery.deliveries.delivered', $delivery) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-circle-check me-1"></i> Mark Delivered
                    </button>
                </form>
            @endif

            @if (in_array($delivery->status, ['delivered', 'failed']))
                <p class="text-muted small mb-0">This delivery is closed ({{ $delivery->statusLabel() }}).</p>
            @endif
        </div>
    </div>
</div>
@endsection
