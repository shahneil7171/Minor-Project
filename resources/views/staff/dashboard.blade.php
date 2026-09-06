@extends('layouts.app')

@section('title', 'Staff Dashboard')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-user-gear me-2" style="color:var(--primary-color);"></i>Staff Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}. Operational overview for the store team.</p>
        </div>
        <span class="badge" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); font-size:.8rem; padding:.5rem .9rem;">
            <i class="fas fa-id-badge me-1"></i> Account Type: {{ auth()->user()->accountTypeLabel() }}
        </span>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total Orders'   => [$stats['total_orders'], 'fa-clipboard-list', 'var(--primary-color)'],
            'Pending'        => [$stats['pending_orders'], 'fa-hourglass-half', '#f59e0b'],
            'Active Orders'  => [$stats['active_orders'], 'fa-truck-fast', '#6366f1'],
            'Delivered'      => [$stats['delivered_orders'], 'fa-circle-check', 'var(--success-color)'],
            'Customers'      => [$stats['customers'], 'fa-users', '#0ea5e9'],
        ] as $label => [$value, $icon, $color])
            <div class="col-6 col-md-4 col-lg">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:46px; height:46px; background:{{ $color }}18; color:{{ $color }}; flex-shrink:0;">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fw-bold fs-4">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Orders</h5>
            <a href="{{ route('staff.orders.index') }}" class="btn btn-sm btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">View All Orders</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="fw-bold">{{ $order->order_number }}</td>
                                <td>{{ $order->user?->name ?? $order->customer_email }}</td>
                                <td><span class="badge bg-secondary">{{ $order->statusLabel() }}</span></td>
                                <td class="text-end">${{ number_format($order->total, 2) }}</td>
                                <td class="text-end text-muted small">{{ $order->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm">
        <i class="fas fa-circle-info me-2" style="color:var(--primary-color);"></i>
        Staff accounts have read-only operational access. Account types and permissions are managed by administrators in the Admin Panel.
    </div>
</div>
@endsection
