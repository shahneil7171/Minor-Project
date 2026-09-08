@extends('layouts.app')

@section('title', 'Seller Payment Profiles')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Seller Payment Profiles</h1>
        <p class="text-muted mb-0">Verification is a manual college-project flow. Bank account numbers are always masked — the full value is never displayed anywhere in KDP MART.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Seller</th>
                            <th>UPI ID</th>
                            <th>Payment Contact</th>
                            <th>QR</th>
                            <th>Bank</th>
                            <th>Account</th>
                            <th>Preferred Method</th>
                            <th>Verification Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $profile->seller?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $profile->seller?->email }}</div>
                                    <div class="small text-muted">ID: #{{ $profile->seller_id }}</div>
                                </td>
                                <td>{{ $profile->upi_id ?? '—' }}</td>
                                <td>
                                    @if ($profile->mobile_number)<div class="small">{{ $profile->mobile_number }}</div>@endif
                                    @if ($profile->payment_email)<div class="small text-muted">{{ $profile->payment_email }}</div>@endif
                                    @if (! $profile->mobile_number && ! $profile->payment_email)—@endif
                                </td>
                                <td>
                                    @if ($profile->qrUrl())
                                        <img src="{{ $profile->qrUrl() }}" alt="QR" style="width:40px; height:40px; object-fit:contain; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;">
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($profile->bank_name)<div class="small">{{ $profile->bank_name }}</div>
                                        @if ($profile->branch_name)<div class="small text-muted">{{ $profile->branch_name }}</div>@endif
                                    @else—@endif
                                </td>
                                {{-- Masked only, even for admins. --}}
                                <td class="font-monospace">{{ $profile->maskedAccountNumber() ?? '—' }}</td>
                                <td>{{ $profile->paymentMethodLabel() }}</td>
                                <td>
                                    <span class="badge {{ $profile->paymentStatusBadge() }}">{{ $profile->paymentStatusLabel() }}</span>
                                    @if ($profile->payment_status === 'rejected' && $profile->rejection_reason)
                                        <div class="small text-danger mt-1" title="{{ $profile->rejection_reason }}">Rejected: {{ \Illuminate\Support\Str::limit($profile->rejection_reason, 40) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.seller-payments.show', $profile) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No seller payment profiles yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($profiles->hasPages())
            <div class="card-footer bg-white d-flex justify-content-center">{{ $profiles->links() }}</div>
        @endif
    </div>
</div>
@endsection
