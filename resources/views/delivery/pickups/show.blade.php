@extends('layouts.app')

@section('title', 'Pickup ' . $pickup->return_number)

@section('content')
<div class="container-fluid" style="max-width:1000px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-rotate-left me-2" style="color:var(--primary-color);"></i>{{ $pickup->return_number }} <span class="badge" style="background:var(--primary-color); vertical-align:middle;">{{ $pickup->statusLabel() }}</span></h1>
            <p class="text-muted mb-0">Return pickup for Order #{{ $pickup->order_number }}</p>
        </div>
        <a href="{{ route('delivery.pickups.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> All pickups</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-user me-1"></i> Pickup from (customer)</h5>
                    <p class="mb-1"><strong>{{ $pickup->order?->shipping_name }}</strong></p>
                    @if ($pickup->order?->shipping_phone)
                        <p class="text-muted mb-1">Phone: {{ $pickup->order->shipping_phone }}</p>
                    @endif
                    <p class="text-muted mb-0">
                        {{ $pickup->order?->shipping_address }}<br>
                        {{ $pickup->order?->shipping_city }}, {{ $pickup->order?->shipping_state }} {{ $pickup->order?->shipping_pincode }}@if($pickup->order?->shipping_country)<br>{{ $pickup->order->shipping_country }}@endif
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-box me-1"></i> Parcel to collect</h5>
                    <p class="mb-1"><strong>{{ $pickup->product_title }}</strong></p>
                    <p class="text-muted mb-1">Quantity: {{ $pickup->quantity }}</p>
                    <p class="text-muted mb-1">Order: #{{ $pickup->order_number }}</p>
                    <p class="text-muted mb-0">Return: {{ $pickup->return_number }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h5 class="card-title"><i class="fas fa-clipboard-list me-1"></i> Pickup instructions</h5>
            @if ($pickup->pickup_notes)
                <p class="mb-1">{{ $pickup->pickup_notes }}</p>
            @else
                <p class="text-muted mb-1">Collect the parcel from the customer and hand it over to the KDP MART seller/warehouse as instructed by the admin.</p>
            @endif
            @if ($pickup->seller)
                <p class="text-muted mb-0">Seller / drop-off: {{ $pickup->seller->name }}</p>
            @endif
        </div>
    </div>

    @if (in_array($pickup->status, ['pickup_assigned', 'pickup_scheduled'], true))
        <form method="POST" action="{{ route('delivery.pickups.collect', ['pickup' => $pickup]) }}"
              onsubmit="return confirm('Mark return {{ $pickup->return_number }} as collected and received?');">
            @csrf
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body d-flex gap-2 flex-wrap align-items-center">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Mark Collected / Received</button>
                    <span class="text-muted small">Confirms the parcel was collected from the customer.</span>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
