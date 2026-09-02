@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Dashboard')

@section('content')
    <div class="stats" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin-bottom:24px;">
        @foreach ([
            'Total Products' => [$stats['total_products'], '#60a5fa'],
            'Total Orders' => [$stats['total_orders'], '#a78bfa'],
            'Total Customers' => [$stats['total_customers'], '#34d399'],
            'Revenue' => ['&#8377;' . number_format($stats['revenue'], 2), '#fbbf24'],
            'Pending Orders' => [$stats['pending_orders'], '#f472b6'],
            'Reviews Pending' => [$stats['pending_reviews'], '#fb923c'],
        ] as $label => [$value, $color])
            <div class="card" style="margin:0;">
                <div style="color:var(--ka-muted); font-size:.74rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:8px;">{{ $label }}</div>
                <div style="font-size:1.55rem; font-weight:800; color:#fff;">{!! $value !!}</div>
            </div>
        @endforeach
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h3>Delivery Workflow</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px;">
            @foreach ([
                'Pending Approval'    => [$deliveryStats['pending_approval'], '#f472b6', route('admin.orders.index', ['status' => 'pending'])],
                'Approved Unassigned' => [$deliveryStats['unassigned'], '#fbbf24', route('admin.deliveries.index')],
                'Assigned'            => [$deliveryStats['assigned'], '#60a5fa', route('admin.deliveries.index')],
                'Out for Delivery'    => [$deliveryStats['out_for_delivery'], '#a78bfa', route('admin.deliveries.index')],
                'Delivered'           => [$deliveryStats['delivered'], '#34d399', route('admin.deliveries.index')],
                'Failed'              => [$deliveryStats['failed'], '#f87171', route('admin.deliveries.index')],
            ] as $label => [$value, $color, $url])
                <a href="{{ $url }}" style="text-decoration:none;">
                    <div style="border:1px solid var(--ka-border); border-radius:10px; padding:12px 14px; background:rgba(255,255,255,.02);">
                        <div style="color:var(--ka-muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">{{ $label }}</div>
                        <div style="font-size:1.25rem; font-weight:800; color:{{ $color }};">{{ $value }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Orders — Last 30 Days</h3>
            <canvas id="ordersChart" height="210"></canvas>
        </div>
        <div class="card">
            <h3>Revenue — Last 30 Days (&#8377;)</h3>
            <canvas id="revenueChart" height="210"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Top Selling Products</h3>
        <div class="table-wrap" style="margin:0;">
            <table>
                <thead>
                    <tr><th>#</th><th>Product</th><th class="num">Units Sold</th><th class="num">Revenue</th></tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $i => $product)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $product->title }}</td>
                            <td class="num">{{ $product->qty }}</td>
                            <td class="num">&#8377;{{ number_format((float) $product->revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var grid = { color: 'rgba(148,163,184,0.15)' };
            var ticks = { color: '#94a3b8' };

            new Chart(document.getElementById('ordersChart'), {
                type: 'line',
                data: {
                    labels: {!! json_encode($orderLabels) !!},
                    datasets: [{
                        label: 'Orders',
                        data: {!! json_encode($orderData) !!},
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.18)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: grid, ticks: ticks }, y: { beginAtZero: true, grid: grid, ticks: Object.assign({ precision: 0 }, ticks) } }
                }
            });

            new Chart(document.getElementById('revenueChart'), {
                type: 'bar',
                data: {
                    labels: {!! json_encode($revLabels) !!},
                    datasets: [{
                        label: 'Revenue',
                        data: {!! json_encode($revData) !!},
                        backgroundColor: 'rgba(16,185,129,0.65)',
                        borderRadius: 5
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: grid, ticks: ticks }, y: { beginAtZero: true, grid: grid, ticks: ticks } }
                }
            });
        })();
    </script>
@endpush