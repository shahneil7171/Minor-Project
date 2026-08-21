@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Customer Reports')

@section('content')
    <div class="page-head">
        <div>
            <h2>Customer Reports</h2>
            <p>Highest spending customers, most orders and newest sign-ups.</p>
        </div>
        <a class="btn green" href="{{ route('admin.reports.customers.export') }}">⬇ Export CSV</a>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Highest Spending Customers</h3>
            <table style="width:100%; border-collapse:collapse;">
                <thead><tr><th>Customer</th><th class="num">Orders</th><th class="num">Spent</th></tr></thead>
                <tbody>
                    @forelse ($topSpending as $c)
                        <tr>
                            <td><a href="{{ route('admin.customers.show', $c->id) }}" style="color:#93c5fd;">{{ $c->name }}</a></td>
                            <td class="num">{{ $c->orders_count }}</td>
                            <td class="num">&#8377;{{ number_format((float) $c->spent, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Most Orders</h3>
            <table style="width:100%; border-collapse:collapse;">
                <thead><tr><th>Customer</th><th class="num">Orders</th></tr></thead>
                <tbody>
                    @forelse ($mostOrders as $c)
                        <tr>
                            <td><a href="{{ route('admin.customers.show', $c->id) }}" style="color:#93c5fd;">{{ $c->name }}</a></td>
                            <td class="num">{{ $c->orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="empty">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Recent Customers</h3>
            <table style="width:100%; border-collapse:collapse;">
                <thead><tr><th>Customer</th><th>Email</th><th>Joined</th></tr></thead>
                <tbody>
                    @forelse ($recent as $c)
                        <tr>
                            <td>{{ $c->name }}</td>
                            <td style="color:var(--ka-muted);">{{ $c->email }}</td>
                            <td>{{ $c->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
