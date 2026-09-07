@extends('layouts.app')

@section('title', 'Seller Payment Profiles')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-indian-rupee-sign me-2" style="color:var(--primary-color);"></i>Seller Payment Profiles</h1>
        <p class="text-muted mb-0">Read-only administration view. Bank account numbers are always masked — the full value is never displayed anywhere in KDP MART.</p>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Seller</th><th>UPI ID</th><th>Payment contact</th><th>QR</th>
                            <th>Bank</th><th>Account</th><th>IFSC</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $profile->seller?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $profile->seller?->email }}</div>
                                </td>
                                <td>{{ $profile->upi_id ?? '—' }}</td>
                                <td>{{ $profile->mobile_number ?? '—' }}</td>
                                <td>
                                    @if ($profile->qrUrl())
                                        <img src="{{ $profile->qrUrl() }}" alt="QR" style="width:40px; height:40px; object-fit:contain; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;">
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $profile->bank_name ?? '—' }}</td>
                                {{-- Masked only, even for admins. --}}
                                <td class="font-monospace">{{ $profile->maskedAccountNumber() ?? '—' }}</td>
                                <td>{{ $profile->ifsc_code ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $profile->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $profile->is_active ? 'Active' : 'Disabled' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No seller payment profiles yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
