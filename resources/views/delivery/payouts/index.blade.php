@extends('layouts.app')

@section('title', 'Cash Refund Payouts')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-hand-holding-dollar me-2" style="color:var(--primary-color);"></i>Cash Refund Payouts</h1>
            <p class="text-muted mb-0">COD refunds assigned to you. Hand the cash to the customer, then confirm the payout.</p>
        </div>
        <a href="{{ route('delivery.dashboard') }}" class="btn btn-outline-secondary">&larr; Dashboard</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Payout</th><th>Order / Return</th><th>Amount</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td><strong>#{{ $payout->id }}</strong></td>
                            <td>
                                @if ($payout->returnRequest)
                                    <div>Return {{ $payout->returnRequest->return_number }}</div>
                                    <div class="text-muted small">Order #{{ $payout->returnRequest->order_number }}</div>
                                @else — @endif
                            </td>
                            <td>₹{{ number_format((float) $payout->amount, 2) }}</td>
                            <td><span class="badge bg-{{ $payout->status === 'confirmed' ? 'success' : ($payout->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $payout->status)) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('delivery.payouts.show', ['payout' => $payout]) }}" class="btn btn-sm btn-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No cash refund payouts assigned.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $payouts->links() }}</div>
@endsection
