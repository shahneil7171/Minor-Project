@extends('layouts.app')

@section('title', 'Product Returns')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-rotate-left me-2" style="color:var(--primary-color);"></i>Product Returns</h1>
        <p class="text-muted mb-0">Return requests raised against products from your store. Approval and refunds are handled by the KDP MART admin team.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('seller.returns.index') }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
        @foreach ($statuses as $s)
            <a href="{{ route('seller.returns.index', ['status' => $s]) }}" class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $statusLabels[$s] }}</a>
        @endforeach
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Return</th>
                            <th>Order</th>
                            <th>Buyer</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($returns as $return)
                            <tr>
                                <td>
                                    <strong>{{ $return->return_number }}</strong>
                                    <div class="text-muted small">{{ $return->created_at->format('M d, Y') }}</div>
                                </td>
                                <td>#{{ $return->order_number }}</td>
                                <td>{{ $return->customer?->name ?? '—' }}</td>
                                <td>{{ $return->product_title }}</td>
                                <td>{{ $return->quantity }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($return->reason, 40) }}</td>
                                <td><span class="badge" style="background:var(--primary-color);">{{ $return->statusLabel() }}</span></td>
                                <td><a href="{{ route('seller.returns.show', ['return' => $return]) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No return requests for your products.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($returns->hasPages())
        <div class="mb-4">{{ $returns->links() }}</div>
    @endif
</div>
@endsection
