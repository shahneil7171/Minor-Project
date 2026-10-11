@extends('layouts.app')

@section('title', 'Cash Refund Payout #' . $payout->id)

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1">Cash Refund Payout #{{ $payout->id }}</h1>
            <p class="text-muted mb-0">
                @if ($payout->returnRequest) For Return {{ $payout->returnRequest->return_number }} · Order #{{ $payout->returnRequest->order_number }} @endif
            </p>
        </div>
        <a href="{{ route('delivery.payouts.index') }}" class="btn btn-outline-secondary">&larr; Payouts</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6">Payout details</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Amount to pay</dt>
                        <dd class="col-7 fw-bold">₹{{ number_format((float) $payout->amount, 2) }}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7"><span class="badge bg-{{ $payout->status === 'confirmed' ? 'success' : ($payout->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_', ' ', $payout->status)) }}</span></dd>
                        <dt class="col-5">Customer</dt>
                        <dd class="col-7">{{ $payout->returnRequest?->customer?->name ?? '—' }}</dd>
                        <dt class="col-5">Product</dt>
                        <dd class="col-7">{{ $payout->returnRequest?->product_title ?? '—' }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">
                        Only the information needed to complete this payout is shown. Seller bank / IFSC and private
                        payment-profile details are never exposed here.
                    </p>
                </div>
            </div>

            @if ($payout->status === 'confirmed')
                <div class="alert alert-success">
                    Confirmed on {{ $payout->confirmed_at?->format('M d, Y h:i A') }}. The refund is completed.
                </div>
            @elseif ($payout->status === 'failed')
                <div class="alert alert-danger">
                    This payout was marked failed. An admin can retry it — a duplicate refund is never created.
                </div>
            @else
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6">Confirm payout</h2>
                        <p class="text-muted small">
                            Hand ₹{{ number_format((float) $payout->amount, 2) }} to the customer and record the
                            customer confirmation (OTP or receipt reference) below. The refund is only completed once
                            this confirmation is captured.
                        </p>
                        <form method="POST" action="{{ route('delivery.payouts.confirm', ['payout' => $payout]) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Customer confirmation (OTP / receipt ref)</label>
                                <input type="text" name="confirmation" class="form-control" minlength="4" maxlength="20" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Note (optional)</label>
                                <input type="text" name="note" class="form-control" maxlength="500">
                            </div>
                            <button type="submit" class="btn btn-success">Confirm payout &amp; complete refund</button>
                        </form>
                        <hr>
                        <div class="d-flex gap-2">
                            @if ($payout->status !== 'in_progress')
                                <form method="POST" action="{{ route('delivery.payouts.start', ['payout' => $payout]) }}">@csrf<button class="btn btn-outline-primary btn-sm">Mark in progress</button></form>
                            @endif
                            <form method="POST" action="{{ route('delivery.payouts.fail', ['payout' => $payout]) }}" onsubmit="return confirm('Mark this payout as failed?');">
                                @csrf
                                <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="max-width:220px;" placeholder="Reason (optional)">
                                <button class="btn btn-outline-danger btn-sm">Mark failed</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
