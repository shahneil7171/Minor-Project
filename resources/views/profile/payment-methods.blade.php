@extends('profile.layout')

@section('profile-content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-credit-card"></i> Saved Payment Methods
        </h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPaymentMethodModal">
            <i class="fas fa-plus"></i> Create Saved Payment Method
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Manage your payment preferences for faster checkout.</p>

        @if ($methods->isEmpty())
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You haven't saved any payment methods yet.
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPaymentMethodModal">
                <i class="fas fa-plus"></i> Add Your First Payment Method
            </button>
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
        @endif
    </div> {{-- Close card-body --}}
</div> {{-- Close Saved Payment Methods card --}}

{{-- Create Payment Method Modal --}}
<div class="modal fade" id="createPaymentMethodModal" tabindex="-1" aria-labelledby="createPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPaymentMethodModalLabel">
                    <i class="fas fa-plus"></i> Create Saved Payment Method
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Select a payment method type:</p>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card h-100 text-center p-3 method-option" data-type="upi" style="cursor:pointer;">
                            <i class="fas fa-mobile-alt fa-2x mb-2 text-primary"></i>
                            <h6>UPI</h6>
                            <small class="text-muted">Save your UPI ID</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center p-3 method-option" data-type="card" style="cursor:pointer;">
                            <i class="fas fa-credit-card fa-2x mb-2 text-primary"></i>
                            <h6>Credit Card</h6>
                            <small class="text-muted">Secure card saving</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center p-3 method-option" data-type="card" style="cursor:pointer;">
                            <i class="fas fa-credit-card fa-2x mb-2 text-primary"></i>
                            <h6>Debit Card</h6>
                            <small class="text-muted">Secure card saving</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 text-center p-3 method-option" data-type="netbanking" style="cursor:pointer;">
                            <i class="fas fa-university fa-2x mb-2 text-primary"></i>
                            <h6>Net Banking</h6>
                            <small class="text-muted">Save bank preference</small>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('profile.payment-methods.store') }}" id="createPaymentMethodForm">
                    @csrf
                    <input type="hidden" name="type" id="selectedMethodType" value="">

                    <div id="upiFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label" for="modal_upi_id">UPI ID</label>
                            <input id="modal_upi_id" name="upi_id" type="text" class="form-control" placeholder="yourname@upi">
                            <div class="form-text">Example: yourname@upi. We will never ask for your UPI PIN.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal_label">Label (optional)</label>
                            <input id="modal_label" name="label" type="text" class="form-control" placeholder="e.g. My GPay">
                        </div>
                    </div>

                    <div id="cardFields" class="d-none">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Secure card saving requires a payment gateway with tokenization. The current application does not have a compatible gateway configured. Raw card numbers and CVVs are never stored.
                        </div>
                    </div>

                    <div id="netbankingFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label" for="modal_bank_code">Bank Code</label>
                            <input id="modal_bank_code" name="bank_code" type="text" class="form-control" placeholder="e.g. SBI">
                            <div class="form-text">We will never ask for your banking password, PIN, or OTP.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal_label_nb">Label (optional)</label>
                            <input id="modal_label_nb" name="label" type="text" class="form-control" placeholder="e.g. My Bank">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal_is_default" name="is_default" value="1">
                            <label class="form-check-label" for="modal_is_default">Set as default</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="saveMethodBtn" disabled>Continue</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var methodOptions = document.querySelectorAll('.method-option');
    var selectedType = document.getElementById('selectedMethodType');
    var upiFields = document.getElementById('upiFields');
    var cardFields = document.getElementById('cardFields');
    var netbankingFields = document.getElementById('netbankingFields');
    var saveBtn = document.getElementById('saveMethodBtn');

    methodOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            methodOptions.forEach(function (o) { o.classList.remove('border-primary'); });
            option.classList.add('border-primary');
            var type = option.getAttribute('data-type');
            selectedType.value = type;

            upiFields.classList.add('d-none');
            cardFields.classList.add('d-none');
            netbankingFields.classList.add('d-none');

            if (type === 'upi') {
                upiFields.classList.remove('d-none');
            } else if (type === 'card') {
            cardFields.classList.remove('d-none');

            // Card saving requires a payment gateway with tokenization.
            saveBtn.disabled = true;
            saveBtn.textContent = 'Card Saving Unavailable';
            return;
            } else if (type === 'netbanking') {
                netbankingFields.classList.remove('d-none');
            }

            saveBtn.disabled = false;
            saveBtn.textContent = 'Continue';
        });
    });
})();
</script>
@endsection