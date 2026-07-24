@extends('profile.layout')

@section('profile-content')
<div class="profile-content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-user-circle"></i> Profile Information
        </h5>
        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Edit Profile
        </a>
    </div>
    <div class="card-body">
        <div class="profile-info-grid">
            <div class="info-item">
                <label class="info-label">Full Name</label>
                <p class="info-value">{{ $user->name }}</p>
            </div>
            <div class="info-item">
                <label class="info-label">Email Address</label>
                <p class="info-value">{{ $user->email }}</p>
            </div>
            <div class="info-item">
                <label class="info-label">Phone Number</label>
                <p class="info-value">{{ $user->phone ?? 'Not provided' }}</p>
            </div>
            <div class="info-item">
                <label class="info-label">Account Status</label>
                <p class="info-value">
                    <span class="badge bg-success">Active</span>
                </p>
            </div>
        </div>

        @if($user->bio)
            <div class="info-item mt-4">
                <label class="info-label">Bio</label>
                <p class="info-value">{{ $user->bio }}</p>
            </div>
        @endif

        <hr>

        <div class="d-grid gap-2">
            <a href="{{ route('profile.change-password') }}" class="btn btn-outline-secondary">
                <i class="fas fa-lock"></i> Change Password
            </a>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-info">
                <h6>Saved Addresses</h6>
                <p class="stat-value">{{ $user->addresses()->count() }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h6>Member Since</h6>
                <p class="stat-value">{{ $user->created_at->format('M d, Y') }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Addresses Summary -->
@if($user->addresses()->count() > 0)
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-home"></i> Saved Addresses
            </h5>
            <a href="{{ route('profile.addresses.index') }}" class="btn btn-sm btn-outline-primary">
                View All
            </a>
            </div>
        <div class="card-body">
            <div class="row">
                @foreach($user->addresses()->limit(2)->get() as $address)
                    <div class="col-md-6 mb-3">
                        <div class="address-preview">
                            <div class="address-badge">
                                @if($address->is_default_shipping)
                                    <span class="badge bg-info">Default Shipping</span>
                                @endif
                                @if($address->is_default_billing)
                                    <span class="badge bg-warning">Default Billing</span>
                                @endif
                            </div>
                            <h6>{{ $address->full_name }}</h6>
                            <p class="text-muted small mb-0">
                                {{ $address->house_number }}, {{ $address->street_address }}<br>
                                {{ $address->city }}, {{ $address->state }} - {{ $address->pincode }}<br>
                                {{ $address->country }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle"></i> No addresses saved yet.
        <a href="{{ route('profile.addresses.create') }}" class="alert-link">Add your first address</a>
    </div>
@endif

<style>
.profile-content-card,
.card {
    background: white;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
    padding: 20px;
    border-radius: 12px 12px 0 0;
}

.card-header h5 {
    color: #333;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.profile-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.info-label {
    display: block;
    font-weight: 600;
    color: #667eea;
    font-size: 0.875rem;
    margin-bottom: 8px;
}

.info-value {
    color: #333;
    margin-bottom: 0;
    font-size: 1rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-icon.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.bg-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-info h6 {
    margin-bottom: 5px;
    color: #666;
    font-size: 0.875rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
    margin: 0;
}

.address-preview {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.address-badge {
    margin-bottom: 10px;
}

.address-preview h6 {
    color: #333;
    margin-bottom: 8px;
    font-weight: 600;
}

.badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    margin-right: 5px;
}
</style>
@endsection
