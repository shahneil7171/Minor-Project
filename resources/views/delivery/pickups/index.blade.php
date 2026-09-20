@extends('layouts.app')

@section('title', 'Return Pickups')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <h1 class="h3 mb-0"><i class="fas fa-rotate-left me-2" style="color:var(--primary-color);"></i>Return Pickups</h1>
        <a href="{{ route('delivery.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-gauge-high me-1"></i> Dashboard</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('delivery.pickups.index') }}" class="btn btn-sm {{ $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}">Active</a>
        @foreach (['received', 'refunded', 'rejected'] as $s)
            <a href="{{ route('delivery.pickups.index', ['status' => $s]) }}" class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $statusLabels[$s] }}</a>
        @endforeach
        <a href="{{ route('delivery.pickups.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Return</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Pickup address</th>
                            <th>Product</th>
                            <th>Scheduled</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pickups as $pickup)
                            <tr>
                                <td><strong>{{ $pickup->return_number }}</strong></td>
                                <td>#{{ $pickup->order_number }}</td>
                                <td>{{ $pickup->order?->shipping_name ?? '—' }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit(($pickup->order?->shipping_address ?? '') . ', ' . ($pickup->order?->shipping_city ?? '') . ' ' . ($pickup->order?->shipping_pincode ?? ''), 48) }}</td>
                                <td class="small">{{ $pickup->product_title }} × {{ $pickup->quantity }}</td>
                                <td class="small">{{ optional($pickup->pickup_scheduled_at, fn ($d) => $d->format('M d, Y h:i A')) ?? '—' }}</td>
                                <td><span class="badge" style="background:var(--primary-color);">{{ $pickup->statusLabel() }}</span></td>
                                <td><a href="{{ route('delivery.pickups.show', ['pickup' => $pickup]) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No return pickups assigned to you.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($pickups->hasPages())
        <div class="mb-4">{{ $pickups->links() }}</div>
    @endif
</div>
@endsection
