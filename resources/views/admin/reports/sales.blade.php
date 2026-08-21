@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Sales Report')

@section('content')
    <div class="page-head">
        <div>
            <h2>Sales Report</h2>
            <p>{{ $totals['orders'] }} order(s) · &#8377;{{ number_format($totals['revenue'], 2) }} revenue in range (cancelled orders excluded).</p>
        </div>
        <a class="btn green" href="{{ route('admin.reports.sales.export', ['period' => $period]) }}">⬇ Export CSV</a>
    </div>

    <div class="filters">
        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
            <a href="{{ route('admin.reports.sales', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card">
        <h3>Revenue trend</h3>
        <canvas id="salesChart" height="120"></canvas>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Period</th><th class="num">Orders</th><th class="num">Revenue (&#8377;)</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr><td>{{ $row[0] }}</td><td class="num">{{ $row[1] }}</td><td class="num">{{ $row[2] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('salesChart'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($labels) !!},
                datasets: [{
                    label: 'Revenue',
                    data: {!! json_encode($totals['chart']) !!},
                    backgroundColor: 'rgba(59,130,246,0.65)',
                    borderRadius: 5
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(148,163,184,.15)' }, ticks: { color: '#94a3b8' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.15)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    </script>
@endpush
