@extends('layouts.app')

@section('title', 'Inventory History')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-history me-2" style="color:var(--primary-color);"></i>Inventory History</h1>
        <p class="text-muted mb-0">Every stock movement on your products, newest first — sales, cancellations, return restocks and manual adjustments.</p>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('seller.inventory.history', ['type' => 'all']) }}"
           class="btn btn-sm {{ $type === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
        @foreach ($types as $key)
            <a href="{{ route('seller.inventory.history', ['type' => $key]) }}"
               class="btn btn-sm {{ $type === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $typeLabels[$key] }}</a>
        @endforeach
        <a href="{{ route('seller.inventory.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">Back to inventory</a>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th class="text-end">Change</th>
                            <th class="text-end">Stock</th>
                            <th>Reference</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td class="small text-muted">{{ $transaction->created_at?->format('d M Y H:i') }}</td>
                                <td class="small">{{ $transaction->product?->title ?? '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $transaction->typeLabel() }}</span></td>
                                <td class="text-end fw-bold {{ $transaction->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $transaction->signedQuantity() }}
                                </td>
                                <td class="text-end small text-muted">{{ $transaction->previous_stock }} &rarr; {{ $transaction->new_stock }}</td>
                                <td class="small text-muted">{{ $transaction->reference ?? $transaction->reason ?? '—' }}</td>
                                <td class="small text-muted">{{ $transaction->actor?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No inventory transactions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-5">{{ $transactions->links() }}</div>
</div>
@endsection
