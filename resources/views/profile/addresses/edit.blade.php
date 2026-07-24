@extends('profile.layout')

@section('profile-content')
<div class="profile-content-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-edit"></i> Edit Address
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('profile.addresses.update', $address) }}" method="POST" class="needs-validation">
            @csrf
            @method('PATCH')

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $address->full_name) }}" required>
                    @error('full_name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $address->phone) }}" required>
                    @error('phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="house_number" class="form-label">House/Building No. <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('house_number') is-invalid @enderror" id="house_number" name="house_number" value="{{ old('house_number', $address->house_number) }}" required>
                    @error('house_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="street_address" class="form-label">Street Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('street_address') is-invalid @enderror" id="street_address" name="street_address" value="{{ old('street_address', $address->street_address) }}" required>
                    @error('street_address')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city', $address->city) }}" required>
                    @error('city')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="state" class="form-label">State/Province <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('state') is-invalid @enderror" id="state" name="state" value="{{ old('state', $address->state) }}" required>
                    @error('state')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="pincode" class="form-label">Pincode/ZIP <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('pincode') is-invalid @enderror" id="pincode" name="pincode" value="{{ old('pincode', $address->pincode) }}" required>
                    @error('pincode')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('country') is-invalid @enderror" id="country" name="country" value="{{ old('country', $address->country) }}" required>
                    @error('country')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="additional_info" class="form-label">Additional Information (Optional)</label>
                <textarea class="form-control @error('additional_info') is-invalid @enderror" id="additional_info" name="additional_info" rows="3" placeholder="e.g., Gate number, building name, etc.">{{ old('additional_info', $address->additional_info) }}</textarea>
                @error('additional_info')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <hr>

            <div class="default-address-options">
                <h6 class="mb-3">Set as Default</h6>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="is_default_shipping" name="is_default_shipping" value="1" {{ old('is_default_shipping', $address->is_default_shipping) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default_shipping">
                        <i class="fas fa-truck text-info"></i> Default Shipping Address
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_default_billing" name="is_default_billing" value="1" {{ old('is_default_billing', $address->is_default_billing) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default_billing">
                        <i class="fas fa-receipt text-warning"></i> Default Billing Address
                    </label>
                </div>
            </div>

            <hr>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Address
                </button>
                <a href="{{ route('profile.addresses.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.text-danger {
    color: #dc3545;
}

.form-control {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px 12px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 5px;
}

.default-address-options {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.default-address-options h6 {
    font-weight: 600;
    color: #333;
}

.form-check {
    padding: 8px 0;
}

.form-check-input {
    border-radius: 4px;
    width: 1.25em;
    height: 1.25em;
    margin-top: 0.3em;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.form-check-label {
    margin-left: 8px;
    user-select: none;
    cursor: pointer;
}

.btn {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-outline-secondary:hover {
    transform: translateY(-2px);
}

hr {
    margin: 2rem 0;
    border: none;
    border-top: 1px solid #eee;
}
</style>
@endsection
