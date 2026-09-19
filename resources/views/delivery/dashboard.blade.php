@extends('layouts.app')

@section('title', 'Delivery Dashboard')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-truck-fast me-2" style="color:var(--primary-color);"></i>Delivery Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}. Here is your delivery workload.</p>
        </div>
        <a href="{{ route('delivery.deliveries.index') }}" class="btn btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">
            <i class="fas fa-box me-1"></i> My Deliveries
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            'Total Assigned'    => [$stats['total'], 'fa-clipboard-list', 'var(--primary-color)'],
            'Pending'           => [$stats['pending'], 'fa-hourglass-half', '#f59e0b'],
            'Ready for Pickup'  => [$stats['ready_for_pickup'], 'fa-boxes-packing', '#0ea5e9'],
            'Picked Up'         => [$stats['picked_up'], 'fa-hand-holding-box', '#6366f1'],
            'Out for Delivery'  => [$stats['out_for_delivery'], 'fa-route', '#8b5cf6'],
            'Delivered'         => [$stats['delivered'], 'fa-circle-check', 'var(--success-color)'],
            'Failed'            => [$stats['failed'], 'fa-circle-exclamation', 'var(--danger-color)'],
        ] as $label => [$value, $icon, $color])
            <div class="col-6 col-md-4 col-lg-3">
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
            <h5 class="mb-0">Assigned Deliveries</h5>
            <span class="badge" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color));">{{ $deliveries->count() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Seller</th>
                            <th>Items</th>
                            <th>Total</th>
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
                                <td class="small text-muted" style="max-width:220px;">
                                    {{ $delivery->order->shipping_address }}, {{ $delivery->order->shipping_city }},
                                    {{ $delivery->order->shipping_state }} {{ $delivery->order->shipping_pincode }}
                                </td>
                                <td class="small">
                                    @php $sellers = $delivery->order->items->map(fn ($i) => $i->seller?->name)->filter()->unique()->values(); @endphp
                                    {{ $sellers->isNotEmpty() ? $sellers->implode(', ') : '—' }}
                                </td>
                                <td>{{ $delivery->order->items->sum('quantity') }}</td>
                                <td class="text-nowrap">&#8377;{{ number_format((float) $delivery->order->total, 2) }}</td>
                                <td><span class="badge" style="background:var(--primary-color);">{{ $delivery->statusLabel() }}</span></td>
                                <td class="small text-muted">{{ $delivery->assigned_at?->format('M d, Y h:i A') }}</td>
                                <td>
                                    <a href="{{ route('delivery.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary text-nowrap">View Delivery</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No active deliveries assigned right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
