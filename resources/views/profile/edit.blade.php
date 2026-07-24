@extends('profile.layout')

@section('profile-content')
<div class="profile-content-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-edit"></i> Edit Profile
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="needs-validation">
            @csrf

            <!-- Profile Photo Section -->
            <div class="photo-upload-section mb-4">
                <h6 class="mb-3">Profile Photo</h6>
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <img id="photoPreview" src="{{ auth()->user()->profile_photo_url }}" alt="Profile Photo" class="profile-photo-preview">
                        @if(auth()->user()->profile_photo_path)
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="deletePhoto()">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        @endif
                    </div>
                    <div class="col-md-9">
                        <div class="upload-area">
                            <input type="file" id="profilePhoto" name="profile_photo" class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*">
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle"></i> Accepted formats: JPEG, PNG, JPG, GIF (Max 2MB)
                            </small>
                            @error('profile_photo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Personal Information -->
            <h6 class="mb-3">Personal Information</h6>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required>
                    @error('phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                <small class="text-muted d-block mt-1">
                    <i class="fas fa-shield-alt"></i> Email is used for login and notifications
                </small>
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="bio" class="form-label">Bio (Optional)</label>
                <textarea class="form-control @error('bio') is-invalid @enderror" id="bio" name="bio" rows="4" placeholder="Tell us about yourself...">{{ old('bio', $user->bio) }}</textarea>
                <small class="text-muted d-block mt-1">Max 500 characters</small>
                @error('bio')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <hr>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Delete Photo Modal -->
<div class="modal fade" id="deletePhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your profile photo? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('profile.deletePhoto') }}" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Image preview on file select
document.getElementById('profilePhoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('photoPreview').src = event.target.result;
        };
        reader.readAsDataURL(file);
    }
});

function deletePhoto() {
    const modal = new bootstrap.Modal(document.getElementById('deletePhotoModal'));
    modal.show();
}
</script>

<style>
.photo-upload-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.profile-photo-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.upload-area {
    padding: 20px;
    border: 2px dashed #667eea;
    border-radius: 8px;
    background-color: #f0f4ff;
}

.upload-area input[type="file"] {
    border: none;
    background-color: transparent;
}

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
</style>
@endsection
