@extends('layouts.app')

@section('title', 'Seller Payment Profile — #' . $profile->seller_id)

@section('content')
<div class="container-fluid" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Seller Payment Profile</h1>
            <p class="text-muted mb-0">
                {{ $profile->seller?->name ?? 'Seller #' . $profile->seller_id }}
                <span class="mx-1">·</span>
                {{ $profile->seller?->email }}
                <span class="mx-1">·</span>
                Seller ID: #{{ $profile->seller_id }}
            </p>
        </div>
        <a href="{{ route('admin.seller-payments.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
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
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        {{-- Verification status --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-shield-halved me-2" style="color:var(--primary-color);"></i>Verification Status</div>
                <div class="card-body">
                    <p class="mb-2"><span class="badge {{ $profile->paymentStatusBadge() }}">{{ $profile->paymentStatusLabel() }}</span></p>
                    @if ($profile->verified_at)
                        <p class="mb-1 small text-muted">Verified: {{ $profile->verified_at->format('M d, Y h:i A') }}</p>
                    @endif
                    @if ($profile->rejection_reason)
                        <p class="mb-1 small"><strong class="text-danger">Rejection reason:</strong><br><span class="text-muted">{{ $profile->rejection_reason }}</span></p>
                    @endif
                    @if ($profile->admin_note)
                        <p class="mb-0 small"><strong>Admin note:</strong><br><span class="text-muted">{{ $profile->admin_note }}</span></p>
                    @endif
                    @if (! $profile->is_active)
                        <p class="mb-0 small text-danger mt-2"><i class="fas fa-circle-pause me-1"></i>Profile is currently paused by the seller.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- UPI --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-mobile-screen me-2" style="color:var(--primary-color);"></i>UPI Payment</div>
                <div class="card-body">
                    <p class="mb-1"><strong>UPI ID:</strong> {{ $profile->upi_id ?? '—' }}</p>
                    <p class="mb-1"><strong>UPI Mobile:</strong> {{ $profile->mobile_number ?? '—' }}</p>
                    <p class="mb-0"><strong>QR Code:</strong></p>
                    @if ($profile->qrUrl())
                        <img src="{{ $profile->qrUrl() }}" alt="UPI QR code" style="width:120px; height:120px; object-fit:contain; border:1px solid rgba(0,0,0,0.1); border-radius:10px; background:#fff; padding:6px; margin-top:6px;">
                    @else
                        <span class="text-muted small">No QR uploaded.</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bank --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-building-columns me-2" style="color:var(--primary-color);"></i>Bank Account Details</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Account Holder:</strong> {{ $profile->account_holder_name ?? '—' }}</p>
                    <p class="mb-1"><strong>Bank:</strong> {{ $profile->bank_name ?? '—' }}</p>
                    <p class="mb-1"><strong>Branch:</strong> {{ $profile->branch_name ?? '—' }}</p>
                    {{-- Masked only, even for admins. --}}
                    <p class="mb-1 font-monospace"><strong>Account:</strong> {{ $profile->maskedAccountNumber() ?? '—' }}</p>
                    <p class="mb-1"><strong>IFSC:</strong> {{ $profile->ifsc_code ?? '—' }}</p>
                    <p class="mb-0"><strong>Account Type:</strong> {{ $profile->accountTypeLabel() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment contact + preference --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-address-card me-2" style="color:var(--primary-color);"></i>Payment Contact &amp; Preference</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Payment Email:</strong> {{ $profile->payment_email ?? '—' }}</p>
                    <p class="mb-1"><strong>Payment Mobile:</strong> {{ $profile->mobile_number ?? '—' }}</p>
                    <p class="mb-0"><strong>Preferred Method:</strong> {{ $profile->paymentMethodLabel() }}</p>
                </div>
            </div>
        </div>

        {{-- Admin actions --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom fw-bold"><i class="fas fa-user-shield me-2" style="color:var(--primary-color);"></i>Admin Actions</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <form method="POST" action="{{ route('admin.seller-payments.verify', $profile) }}" onsubmit="return confirm('Verify these payment details?')">
                            @csrf
                            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i>Verify</button>
                        </form>
                        <form method="POST" action="{{ route('admin.seller-payments.request-update', $profile) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info" title="Ask the seller to refresh their details"><i class="fas fa-rotate me-1"></i>Request Update</button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('admin.seller-payments.reject', $profile) }}">
                        @csrf
                        <label class="form-label small fw-bold" for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="2" class="form-control mb-2" placeholder="Required when rejecting — tell the seller what to fix.">{{ old('rejection_reason') }}</textarea>
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject these payment details?')"><i class="fas fa-ban me-1"></i>Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection