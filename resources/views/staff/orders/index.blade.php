@extends('layouts.app')

@section('title', 'Staff — Orders')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-clipboard-list me-2" style="color:var(--primary-color);"></i>Orders</h1>
            <p class="text-muted mb-0">Read-only operational view of store orders.</p>
        </div>
        <a href="{{ route('staff.dashboard') }}" class="btn btn-outline-primary">← Staff Dashboard</a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('staff.orders.index') }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
        @foreach (\App\Models\Order::STATUSES as $orderStatus)
            <a href="{{ route('staff.orders.index', ['status' => $orderStatus]) }}" class="btn btn-sm {{ $status === $orderStatus ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ \App\Models\Order::STATUS_LABELS[$orderStatus] ?? ucfirst($orderStatus) }}
            </a>
        @endforeach
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-bold">{{ $order->order_number }}</td>
                                <td>{{ $order->user?->name ?? $order->customer_email }}</td>
                                <td class="text-uppercase small text-muted">{{ $order->payment_method }}</td>
                                <td><span class="badge bg-secondary">{{ $order->statusLabel() }}</span></td>
                                <td class="text-end">${{ number_format($order->total, 2) }}</td>
                                <td class="text-end text-muted small">{{ $order->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center mb-5">{{ $orders->links() }}</div>
</div>
@endsection
