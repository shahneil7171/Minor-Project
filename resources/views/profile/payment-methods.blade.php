@extends('profile.layout')

@section('profile-content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-credit-card"></i> Saved Payment Methods
        </h5>
    </div>
    <div class="card-body">
        @if ($methods->isEmpty())
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payment methods saved yet.
            </div>
        @else
            <div class="row g-3">
                @foreach ($methods as $method)
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            {{ $method->typeLabel() }}
                                            @if ($method->is_default)
                                                <span class="badge bg-success ms-1">Default</span>
                                            @endif
                                        </h6>
                                        <p class="text-muted small mb-0">{{ $method->displayLabel() }}</p>
                                        @if ($method->label)
                                            <p class="text-muted small mb-0">{{ $method->label }}</p>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        @if (! $method->is_default)
                                            <form method="POST" action="{{ route('profile.payment-methods.set-default', $method) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Set as default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('profile.payment-methods.destroy', $method) }}" class="d-inline" onsubmit="return confirm('Remove this payment method?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        <hr class="my-4">

        <h6 class="mb-3"><i class="fas fa-plus"></i> Add a Payment Method</h6>
        <form method="POST" action="{{ route('profile.payment-methods.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-select" required>
                        <option value="upi">UPI</option>
                        <option value="card">Card (metadata only)</option>
                        <option value="netbanking">Net Banking</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="label">Label (optional)</label>
                    <input id="label" name="label" type="text" class="form-control" placeholder="e.g. My GPay">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="upi_id">UPI ID</label>
                    <input id="upi_id" name="upi_id" type="text" class="form-control" placeholder="yourname@upi">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label" for="card_brand">Card Brand</label>
                    <input id="card_brand" name="card_brand" type="text" class="form-control" placeholder="Visa">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="card_last4">Last 4 Digits</label>
                    <input id="card_last4" name="card_last4" type="text" class="form-control" placeholder="1234" maxlength="4">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="card_expiry_month">Expiry Month</label>
                    <input id="card_expiry_month" name="card_expiry_month" type="number" class="form-control" placeholder="MM" min="1" max="12">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="card_expiry_year">Expiry Year</label>
                    <input id="card_expiry_year" name="card_expiry_year" type="number" class="form-control" placeholder="YYYY" min="2024" max="2040">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label" for="bank_code">Bank Code (Net Banking)</label>
                    <input id="bank_code" name="bank_code" type="text" class="form-control" placeholder="e.g. SBI">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1">
                        <label class="form-check-label" for="is_default">Set as default</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Payment Method</button>
            </div>
        </form>

        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>Security note:</strong> Raw card numbers and CVVs are never stored. Card entries save only brand, masked last four digits, and expiry. UPI IDs are saved for convenience and do not authorize payments.
        </div>
    </div>
</div>
@endsection
        @endif
