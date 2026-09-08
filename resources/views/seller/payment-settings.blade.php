@extends('layouts.app')

@section('title', 'Payment Details')

@section('content')
<div class="container-fluid" style="max-width:900px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Payment Details</h1>
        <p class="text-muted mb-0">Add your payment information so you can receive payments for your products.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
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

    <form method="POST" action="{{ route('seller.payment-settings.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- ============ A. UPI PAYMENT ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-mobile-screen me-2"></i>UPI Payment</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="upi_id">UPI ID</label>
                        <input id="upi_id" name="upi_id" type="text" value="{{ old('upi_id', $profile->upi_id) }}" placeholder="yourname@upi" class="form-control @error('upi_id') is-invalid @enderror">
                        <div class="form-text">Examples: yourname@oksbi, yourname@okaxis, yourshop@paytm</div>
                        @error('upi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_number">UPI Mobile Number</label>
                        <input id="mobile_number" name="mobile_number" type="text" value="{{ old('mobile_number', $profile->mobile_number) }}" placeholder="Enter mobile number" class="form-control @error('mobile_number') is-invalid @enderror">
                        <div class="form-text">Customer-facing contact for UPI payments (not your private profile phone).</div>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ QR CODE ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-qrcode me-2"></i>UPI QR Code</h2>
                <p class="text-muted small mb-3">Upload your UPI QR code so customers can scan and pay. Accepted types: JPG, JPEG, PNG, WEBP (max 2 MB).</p>
                <div class="d-flex flex-wrap gap-4 align-items-start">
                    @if ($profile->qrUrl())
                        <div class="text-center">
                            <img src="{{ $profile->qrUrl() }}" alt="Your payment QR code" style="width:160px; height:160px; object-fit:contain; border-radius:12px; border:1px solid rgba(0,0,0,0.1); background:#fff; padding:8px;">
                            <div class="form-text mt-1">Current QR code</div>
                        </div>
                    @endif
                    <div class="flex-fill" style="min-width:260px;">
                        <label class="form-label" for="qr_code">
                            @if ($profile->qrUrl())
                                Replace QR code
                            @else
                                Upload QR code
                            @endif
                        </label>
                        <input id="qr_code" name="qr_code" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('qr_code') is-invalid @enderror">
                        @if ($profile->qrUrl())
                            <div class="form-text">Uploading a new image replaces the current one.</div>
                        @endif
                        <div class="form-text">Never upload executable or non-image files. Max 2 MB.</div>
                        @error('qr_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                @if ($profile->qrUrl())
                    <div class="mt-3">
                        <form method="POST" action="{{ route('seller.payment-settings.qr.remove') }}" onsubmit="return confirm('Remove your QR code?')" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Remove QR</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ C. BANK ACCOUNT DETAILS ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-building-columns me-2"></i>Bank Account Details</h2>
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
                        <label class="form-label" for="ifsc_code">IFSC Code</label>
                        <input id="ifsc_code" name="ifsc_code" type="text" value="{{ old('ifsc_code', $profile->ifsc_code) }}" placeholder="e.g. SBIN0001234" class="form-control @error('ifsc_code') is-invalid @enderror" style="text-transform:uppercase;">
                        @error('ifsc_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_number">Account Number</label>
                        <input id="account_number" name="account_number" type="text" value="" placeholder="{{ $profile->maskedAccountNumber() ?? '6-20 digit account number' }}" autocomplete="off" class="form-control @error('account_number') is-invalid @enderror">
                        <div class="form-text">
                            @if ($profile->maskedAccountNumber())
                                Stored on file: <strong>{{ $profile->maskedAccountNumber() }}</strong> — leave blank to keep it.
                            @else
                                Leave blank if you prefer UPI/QR only.
                            @endif
                        </div>
                        @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_account_number">Confirm Account Number</label>
                        <input id="confirm_account_number" name="confirm_account_number" type="text" value="" placeholder="Re-enter account number" autocomplete="off" class="form-control @error('confirm_account_number') is-invalid @enderror">
                        <div class="form-text">Must match the account number above.</div>
                        @error('confirm_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_type">Account Type</label>
                        <select id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror">
                            <option value="">— Select —</option>
                            @foreach (\App\Models\SellerPaymentProfile::ACCOUNT_TYPES as $type)
                                <option value="{{ $type }}" {{ old('account_type', $profile->account_type) === $type ? 'selected' : '' }}>{{ \App\Models\SellerPaymentProfile::ACCOUNT_TYPE_LABELS[$type] }}</option>
                            @endforeach
                        </select>
                        @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="alert alert-light border mt-3 mb-0 small">
                    <i class="fas fa-shield-halved me-1"></i>
                    <strong>Security note:</strong> Bank details are private and are not visible to other sellers, buyers or delivery partners. The account number is stored encrypted and is only ever shown to you in masked form (XXXXXX1234). Admins also see it masked only.
                </div>
            </div>
        </div>

        {{-- ============ D. PAYMENT CONTACT ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-address-card me-2"></i>Payment Contact</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="payment_email">Payment Email</label>
                        <input id="payment_email" name="payment_email" type="email" value="{{ old('payment_email', $profile->payment_email) }}" placeholder="payouts@example.com" class="form-control @error('payment_email') is-invalid @enderror">
                        <div class="form-text">The email where you want to receive payment notifications (separate from your login email).</div>
                        @error('payment_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_mobile_display">Payment Mobile Number</label>
                        <input id="payment_mobile_display" type="text" value="{{ old('mobile_number', $profile->mobile_number) }}" placeholder="Enter mobile number" class="form-control" disabled>
                        <div class="form-text">Set via the "UPI Mobile Number" field above.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ E. PAYMENT PREFERENCE ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-sliders me-2"></i>Payment Preference</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="payment_method">Preferred Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            <option value="">— Select —</option>
                            @foreach (\App\Models\SellerPaymentProfile::PAYMENT_METHODS as $method)
                                <option value="{{ $method }}" {{ old('payment_method', $profile->payment_method) === $method ? 'selected' : '' }}>{{ \App\Models\SellerPaymentProfile::PAYMENT_METHOD_LABELS[$method] }}</option>
                            @endforeach
                        </select>
                        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $profile->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Payment profile active — show my UPI/QR to buyers at checkout</label>
                </div>
            </div>
        </div>

        {{-- ============ F. VERIFICATION STATUS ============ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-shield-halved me-2"></i>Payment Details Status</h2>
                <p class="mb-2"><span class="badge {{ $profile->paymentStatusBadge() }}">{{ $profile->paymentStatusLabel() }}</span></p>
                @if ($profile->isVerified())
                    <p class="text-muted small mb-0"><i class="fas fa-check-circle me-1"></i>Your payment details have been verified by the store.</p>
                @elseif ($profile->payment_status === 'rejected' && $profile->rejection_reason)
                    <p class="text-danger small mb-1"><i class="fas fa-circle-xmark me-1"></i>Rejected. Reason:</p>
                    <p class="text-muted small mb-0">{{ $profile->rejection_reason }}</p>
                @elseif ($profile->payment_status === 'request_update' && $profile->admin_note)
                    <p class="text-info small mb-1"><i class="fas fa-circle-info me-1"></i>Update requested. Note:</p>
                    <p class="text-muted small mb-0">{{ $profile->admin_note }}</p>
                @else
                    <p class="text-muted small mb-0">Saving your details marks them as configured. An administrator will manually verify them. Storing details does not automatically settle payments.</p>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-5">
            <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">{{ $profile->exists ? 'Update Payment Details' : 'Save Payment Details' }}</button>
            <a href="{{ route('seller.orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection