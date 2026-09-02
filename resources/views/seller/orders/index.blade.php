@extends('layouts.app')

@section('title', 'Store Orders')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-clipboard-list me-2" style="color:var(--primary-color);"></i>Store Orders</h1>
        <p class="text-muted mb-0">Orders containing products from your store.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('seller.orders.index') }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
        @foreach ($statuses as $s)
            <a href="{{ route('seller.orders.index', ['status' => $s]) }}" class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $statusLabels[$s] }}</a>
        @endforeach
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th><th>Date</th><th>Your Items</th><th>Total Qty</th>
                            <th>Order Status</th><th>Delivery</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-bold">#{{ $order->order_number }}</td>
                                <td class="small text-muted">{{ $order->created_at->format('M d, Y') }}</td>
                                <td>
                                    @foreach ($order->items as $item)
                                        <div class="small">{{ $item->product_title }} &times; {{ $item->quantity }}</div>
                                    @endforeach
                                </td>
                                <td>{{ $order->items->sum('quantity') }}</td>
                                <td><span class="badge" style="background:var(--primary-color);">{{ $order->statusLabel() }}</span></td>
                                <td class="small">{{ $order->delivery?->statusLabel() ?? '—' }}</td>
                                <td><a href="{{ route('seller.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No orders found for this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($orders->hasPages())
        <div class="d-flex justify-content-center mb-5">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
