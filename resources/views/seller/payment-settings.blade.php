@extends('layouts.app')

@section('title', 'Payment Settings')

@section('content')
<div class="container-fluid" style="max-width:900px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Payment Settings</h1>
        <p class="text-muted mb-0">Buyers see these details when they pay for your products by UPI/QR at checkout.</p>
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

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-mobile-screen me-2"></i>UPI Payment</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="upi_id">UPI ID</label>
                        <input id="upi_id" name="upi_id" type="text" value="{{ old('upi_id', $profile->upi_id) }}" placeholder="yourname@upi" class="form-control @error('upi_id') is-invalid @enderror">
                        <div class="form-text">Shown at checkout so buyers can pay you directly (e.g. seller@upi).</div>
                        @error('upi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_number">Payment mobile number</label>
                        <input id="mobile_number" name="mobile_number" type="text" value="{{ old('mobile_number', $profile->mobile_number) }}" placeholder="10-digit number" class="form-control @error('mobile_number') is-invalid @enderror">
                        <div class="form-text">Customer-facing contact for UPI payments (not your private profile phone).</div>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-qrcode me-2"></i>QR Code</h2>
                <div class="d-flex flex-wrap gap-4 align-items-start">
                    @if ($profile->qrUrl())
                        <div class="text-center">
                            <img src="{{ $profile->qrUrl() }}" alt="Your payment QR code" style="width:160px; height:160px; object-fit:contain; border-radius:12px; border:1px solid rgba(0,0,0,0.1); background:#fff; padding:8px;">
                            <div class="form-text mt-1">Current QR code</div>
                        </div>
                    @else
                        <div class="text-center text-muted" style="width:160px;">
                            <div style="width:160px; height:160px; display:flex; align-items:center; justify-content:center; border:1px dashed rgba(0,0,0,0.2); border-radius:12px; background:rgba(0,0,0,0.02);">
                                <i class="fas fa-qrcode fa-2x"></i>
                            </div>
                            <div class="form-text mt-1">No QR code uploaded yet</div>
                        </div>
                    @endif
                    <div class="flex-grow-1" style="min-width:220px;">
                        <label class="form-label" for="qr_code">{{ $profile->qrUrl() ? 'Replace QR code' : 'Upload QR code' }}</label>
                        <input id="qr_code" name="qr_code" type="file" accept="image/png,image/jpeg,image/webp" class="form-control @error('qr_code') is-invalid @enderror">
                        <div class="form-text">PNG, JPG or WebP image up to 2&nbsp;MB. Uploading a new image replaces the current one.</div>
                        @error('qr_code')<div class="invalid-feedback">{{ $message }}</div>@enderror

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="fas fa-building-columns me-2"></i>Bank Details <span class="badge bg-secondary">Optional</span></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="account_holder_name">Account holder name</label>
                        <input id="account_holder_name" name="account_holder_name" type="text" value="{{ old('account_holder_name', $profile->account_holder_name) }}" class="form-control @error('account_holder_name') is-invalid @enderror">
                        @error('account_holder_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="bank_name">Bank name</label>
                        <input id="bank_name" name="bank_name" type="text" value="{{ old('bank_name', $profile->bank_name) }}" class="form-control @error('bank_name') is-invalid @enderror">
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_number">Account number</label>
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
                        <label class="form-label" for="ifsc_code">IFSC code</label>
                        <input id="ifsc_code" name="ifsc_code" type="text" value="{{ old('ifsc_code', $profile->ifsc_code) }}" placeholder="e.g. SBIN0001234" class="form-control @error('ifsc_code') is-invalid @enderror" style="text-transform:uppercase;">
                        @error('ifsc_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="alert alert-light border mt-3 mb-0 small">
                    <i class="fas fa-shield-halved me-1"></i>
                    <strong>Security note:</strong> Bank details are private and are not visible to other sellers, buyers or delivery partners. The account number is stored encrypted and is only ever shown to you in masked form (XXXXXX1234). Admins also see it masked only.
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $profile->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Payment profile active — show my UPI/QR to buyers at checkout</label>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-5">
            <button type="submit" class="btn btn-primary">{{ $profile->exists ? 'Update Payment Details' : 'Save Payment Details' }}</button>
        </div>
    </form>

    @if ($profile->qrUrl())
        <form method="POST" action="{{ route('seller.payment-settings.qr.remove') }}" class="mb-5" onsubmit="return confirm('Remove your QR code?')">
            @csrf
            <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Remove QR Code</button>
        </form>
    @endif
</div>
@endsection

                    </div>
                </div>
            </div>
        </div>
