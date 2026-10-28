@extends('layouts.app')

@section('title', 'Admin Payment Settings')

@section('content')
<div class="container-fluid" style="max-width:900px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Admin Payment Settings</h1>
        <p class="text-muted mb-0">Configure the store's payment details so buyers can pay for KDP MART-owned products. Bank account numbers are stored encrypted and shown masked only.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Please check the highlighted fields.</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payment-settings.update') }}" enctype="multipart/form-data" id="paymentForm">
        @csrf
        <input type="hidden" name="remove_qr" id="removeQrFlag" value="0">

        {{-- ============ A. UPI PAYMENT ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-mobile-screen me-2"></i>UPI Payment</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="upi_id">UPI ID</label>
                        <input id="upi_id" name="upi_id" type="text" value="{{ old('upi_id', $profile->upi_id) }}" placeholder="store@upi" class="form-control @error('upi_id') is-invalid @enderror">
                        <div class="form-text">Examples: kdpmart@oksbi, kdpmart@okaxis, store@paytm</div>
                        @error('upi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_number">UPI Mobile Number</label>
                        <input id="mobile_number" name="mobile_number" type="text" value="{{ old('mobile_number', $profile->mobile_number) }}" placeholder="Enter mobile number" class="form-control @error('mobile_number') is-invalid @enderror">
                        <div class="form-text">Customer-facing contact for UPI payments.</div>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ QR CODE ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-qrcode me-2"></i>UPI QR Code</h2>
                <p class="text-muted small mb-3">Upload the store's UPI QR code so customers can scan and pay. Accepted types: JPG, JPEG, PNG, WEBP (max 2 MB).</p>
                <div class="d-flex flex-wrap gap-4 align-items-start">
                    @if ($profile->qrUrl())
                        <div class="text-center" id="qrPreviewContainer">
                            <img src="{{ $profile->qrUrl() }}" alt="QR Code" id="qrPreview" style="width:180px; height:180px; object-fit:contain; border:1px solid #ddd; border-radius:8px; background:#fff;">
                            <div class="mt-2">
                                <button type="button" id="removeQrBtn" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Remove QR</button>
                                <button type="button" id="undoRemoveQr" class="btn btn-sm btn-outline-secondary d-none"><i class="fas fa-undo me-1"></i>Undo</button>
                            </div>
                            <p id="qrRemoveNotice" class="text-danger small mt-2 d-none">QR code will be removed when you save.</p>
                        </div>
                    @else
                        <div class="text-center" id="qrPreviewContainer">
                            <div class="border rounded d-flex align-items-center justify-content-center" style="width:180px; height:180px; color:#6c757d;">No QR code uploaded</div>
                            <div class="mt-2">
                                <button type="button" id="removeQrBtn" class="btn btn-sm btn-outline-danger d-none"><i class="fas fa-trash me-1"></i>Remove QR</button>
                                <button type="button" id="undoRemoveQr" class="btn btn-sm btn-outline-secondary d-none"><i class="fas fa-undo me-1"></i>Undo</button>
                            </div>
                        </div>
                    @endif
                    <div>
                        <label class="form-label d-block mb-2">Upload QR Code</label>
                        <input id="qr_code" name="qr_code" type="file" class="form-control @error('qr_code') is-invalid @enderror" accept="image/png,image/jpg,image/jpeg,image/webp">
                        @error('qr_code')<div class="invalid-feedback mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        {{-- ============ B. BANK DETAILS ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-university me-2"></i>Bank Account Details</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="account_holder_name">Account Holder Name</label>
                        <input id="account_holder_name" name="account_holder_name" type="text" value="{{ old('account_holder_name', $profile->account_holder_name) }}" class="form-control @error('account_holder_name') is-invalid @enderror">
                        @error('account_holder_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="bank_name">Bank Name</label>
                        <input id="bank_name" name="bank_name" type="text" value="{{ old('bank_name', $profile->bank_name) }}" class="form-control @error('bank_name') is-invalid @enderror">
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="branch_name">Branch Name</label>
                        <input id="branch_name" name="branch_name" type="text" value="{{ old('branch_name', $profile->branch_name) }}" class="form-control @error('branch_name') is-invalid @enderror">
                        @error('branch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_number">Account Number</label>
                        <input id="account_number" name="account_number" type="text" value="{{ old('account_number', $maskedAccountNumber ?? '') }}" placeholder="XXXXXX1234" class="form-control @error('account_number') is-invalid @enderror" readonly>
                        <div class="form-text">Full number is stored encrypted and never shown in full. Enter a new number below to replace it.</div>
                        @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_account_number">Confirm Account Number</label>
                        <input id="confirm_account_number" name="confirm_account_number" type="text" placeholder="Re-enter to confirm" class="form-control @error('confirm_account_number') is-invalid @enderror">
                        <div class="form-text">Required when entering a new account number.</div>
                        @error('confirm_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ifsc_code">IFSC Code</label>
                        <input id="ifsc_code" name="ifsc_code" type="text" value="{{ old('ifsc_code', $profile->ifsc_code) }}" placeholder="SBIN0001234" class="form-control @error('ifsc_code') is-invalid @enderror">
                        @error('ifsc_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_type">Account Type</label>
                        <select id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            <option value="savings" {{ old('account_type', $profile->account_type) === 'savings' ? 'selected' : '' }}>Savings</option>
                            <option value="current" {{ old('account_type', $profile->account_type) === 'current' ? 'selected' : '' }}>Current</option>
                        </select>
                        @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-1"></i>
                    The stored account number is encrypted. If you do not enter a new account number, the existing one is kept unchanged.
                </div>
            </div>
        </div>
        {{-- ============ C. CONTACT & PREFERENCE ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-address-card me-2"></i>Payment Contact &amp; Preference</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="payment_email">Payment Contact Email</label>
                        <input id="payment_email" name="payment_email" type="email" value="{{ old('payment_email', $profile->payment_email) }}" class="form-control @error('payment_email') is-invalid @enderror">
                        @error('payment_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_method">Preferred Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            <option value="upi" {{ old('payment_method', $profile->payment_method) === 'upi' ? 'selected' : '' }}>UPI</option>
                            <option value="bank_transfer" {{ old('payment_method', $profile->payment_method) === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="both" {{ old('payment_method', $profile->payment_method) === 'both' ? 'selected' : '' }}>Both (UPI + Bank Transfer)</option>
                        </select>
                        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $profile->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Payment profile active — show the store's UPI/QR to buyers at checkout</label>
                </div>
            </div>
        </div>

        {{-- ============ D. VERIFICATION STATUS ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-shield-halved me-2"></i>Payment Details Status</h2>
                <p class="mb-2"><span class="badge {{ $profile->paymentStatusBadge() }}">{{ $profile->paymentStatusLabel() }}</span></p>
                @if ($profile->isVerified())
                    <p class="text-muted small mb-0"><i class="fas fa-check-circle me-1"></i>This payment profile has been verified.</p>
                @elseif ($profile->payment_status === 'rejected')
                    <p class="text-danger small mb-1"><i class="fas fa-circle-xmark me-1"></i>Rejected.</p>
                @elseif ($profile->payment_status === 'request_update')
                    <p class="text-info small mb-1"><i class="fas fa-circle-info me-1"></i>Update requested.</p>
                @else
                    <p class="text-muted small mb-0">Saving your details marks them as configured. Storing details does not automatically settle or verify payments.</p>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-5">
            <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">{{ $profile->exists ? 'Update Payment Details' : 'Save Payment Details' }}</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

@section('extra-scripts')
<script>
(function () {
    var removeBtn = document.getElementById('removeQrBtn');
    var undoBtn = document.getElementById('undoRemoveQr');
    var flag = document.getElementById('removeQrFlag');
    var notice = document.getElementById('qrRemoveNotice');
    var preview = document.getElementById('qrPreviewContainer');
    var fileInput = document.getElementById('qr_code');

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (flag) flag.value = '1';
            if (notice) notice.classList.remove('d-none');
            if (preview) preview.style.display = 'none';
            if (fileInput) fileInput.value = '';
            removeBtn.classList.add('d-none');
            if (undoBtn) undoBtn.classList.remove('d-none');
        });
    }

    if (undoBtn) {
        undoBtn.addEventListener('click', function () {
            if (flag) flag.value = '0';
            if (notice) notice.classList.add('d-none');
            if (preview) preview.style.display = '';
            removeBtn.classList.remove('d-none');
            if (undoBtn) undoBtn.classList.add('d-none');
        });
    }
})();
</script>
@endsection
</div>
@endsection
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
