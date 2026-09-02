@extends('layouts.app')

@section('title', $status === 'all' ? 'Delivery History' : 'My Deliveries')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <h1 class="h3 mb-0"><i class="fas fa-box me-2" style="color:var(--primary-color);"></i>{{ $status === 'all' ? 'Delivery History' : 'My Deliveries' }}</h1>
        <a href="{{ route('delivery.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-gauge-high me-1"></i> Dashboard</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('delivery.deliveries.index') }}" class="btn btn-sm {{ $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}" {{ $status === 'active' ? 'aria-current="true"' : '' }}>Active</a>
        @foreach ($statuses as $s)
            <a href="{{ route('delivery.deliveries.index', ['status' => $s]) }}" class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $statusLabels[$s] }}</a>
        @endforeach
        <a href="{{ route('delivery.deliveries.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">History</a>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Items</th>
                            <th>Order Status</th>
                            <th>Delivery Status</th>
                            <th>Assigned</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deliveries as $delivery)
                            <tr>
                                <td class="fw-bold">#{{ $delivery->order->order_number }}</td>
                                <td>{{ $delivery->order->shipping_name }}</td>
                                <td>{{ $delivery->order->shipping_phone }}</td>
                                <td class="small text-muted" style="max-width:200px;">
                                    {{ $delivery->order->shipping_address }}, {{ $delivery->order->shipping_city }},
                                    {{ $delivery->order->shipping_state }} {{ $delivery->order->shipping_pincode }}
                                </td>
                                <td>{{ $delivery->order->items->sum('quantity') }}</td>
                                <td class="small">{{ $delivery->order->statusLabel() }}</td>
                                <td><span class="badge" style="background:var(--primary-color);">{{ $delivery->statusLabel() }}</span></td>
                                <td class="small text-muted">{{ $delivery->assigned_at?->format('M d, Y h:i A') }}</td>
                                <td><a href="{{ route('delivery.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No deliveries found for this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($deliveries->hasPages())
        <div class="d-flex justify-content-center mb-5">{{ $deliveries->links() }}</div>
    @endif
</div>
@endsection
