@extends('layouts.app')

@section('title', 'Staff Stock History')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-clock-rotate-left me-2" style="color:var(--primary-color);"></i>Stock History</h1>
            <p class="text-muted mb-0">Recent stock movements recorded for the store.</p>
        </div>
        <a href="{{ route('staff.inventory.index') }}" class="btn btn-sm btn-outline-secondary">Back to Inventory</a>
    </div>

    <form method="GET" action="{{ route('staff.inventory.history') }}" class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="input-group">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search by reference or reason">
                <button class="btn btn-primary" type="submit" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">Search</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Previous</th>
                        <th class="text-end">New</th>
                        <th>Actor</th>
                        <th>Date</th>
                        <th>Reason / Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td>{{ $t->product?->title ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $t->typeLabel() ?? $t->type }}</span></td>
                            <td class="text-end">{{ $t->signedQuantity() }}</td>
                            <td class="text-end">{{ $t->previous_stock }}</td>
                            <td class="text-end">{{ $t->new_stock }}</td>
                            <td>{{ $t->actor?->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $t->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-muted small">{{ $t->reason ?? $t->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        @if ($transactions->onFirstPage())
            <span class="text-muted">Previous</span>
        @else
            <a href="{{ $transactions->previousPageUrl() }}" class="btn btn-sm btn-outline-secondary">Previous</a>
        @endif
        <span class="text-muted small">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>
        @if ($transactions->hasMorePages())
            <a href="{{ $transactions->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary">Next</a>
        @else
            <span class="text-muted">Next</span>
        @endif
    </div>
</div>
@endsection