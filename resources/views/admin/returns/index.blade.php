@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Returns')

@section('content')
    <div class="page-head">
        <div>
            <h2>Product Returns</h2>
            <p>Track return requests against customer orders.</p>
        </div>
        <a class="btn" href="{{ route('admin.returns.create') }}">+ New Return</a>
    </div>

    <div class="filters">
        <a href="{{ route('admin.returns.index') }}" class="{{ $status === 'all' ? 'active' : '' }}">All</a>
        @foreach ($statuses as $s)
            <a href="{{ route('admin.returns.index', ['status' => $s]) }}" class="{{ $status === $s ? 'active' : '' }}">{{ $statusLabels[$s] }}</a>
        @endforeach
    </div>

    <div class="filters">
        <a href="{{ route('admin.returns.index', array_merge(request()->query(), ['refund' => 'all'])) }}" class="{{ ($refund ?? 'all') === 'all' ? 'active' : '' }}">All refunds</a>
        @foreach ($refundStatuses as $rs)
            <a href="{{ route('admin.returns.index', array_merge(request()->query(), ['refund' => $rs])) }}" class="{{ ($refund ?? 'all') === $rs ? 'active' : '' }}">{{ $refundLabels[$rs] }}</a>
        @endforeach
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Return</th><th>Order</th><th>Customer</th><th>Seller</th><th>Product</th><th>Qty</th><th>Status</th><th>Refund</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($returns as $return)
                    <tr>
                        <td><strong>#{{ $return->id }}</strong><div style="color:var(--ka-muted); font-size:.78rem;">{{ $return->created_at->format('M d, Y') }}</div></td>
                        <td>{{ $return->order_number ?: '—' }}</td>
                        <td>
                            {{ $return->customer?->name ?? '—' }}
                            @if ($return->customer_email)
                                <div style="color:var(--ka-muted); font-size:.78rem;">{{ $return->customer_email }}</div>
                            @endif
                        </td>
                        <td>{{ $return->seller?->name ?? '—' }}</td>
                        <td>{{ $return->product_title }}</td>
                        <td>{{ $return->quantity }}</td>
                        <td><span class="badge {{ $return->status }}">{{ $statusLabels[$return->status] }}</span></td>
                        <td>
                            <span class="badge {{ $return->refund_status }}">{{ $refundLabels[$return->refund_status] ?? ucfirst($return->refund_status) }}</span>
                            @if ((float) $return->refund_amount > 0)
                                <div style="color:var(--ka-muted); font-size:.78rem;">&#8377;{{ number_format((float) $return->refund_amount, 2) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <a class="primary" href="{{ route('admin.returns.show', $return) }}">View</a>
                                <form method="POST" action="{{ route('admin.returns.destroy', $return) }}" onsubmit="return confirm('Delete return #{{ $return->id }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No returns found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($returns->hasPages())
        <div class="pagination">{{ $returns->links() }}</div>
    @endif
@endsection
