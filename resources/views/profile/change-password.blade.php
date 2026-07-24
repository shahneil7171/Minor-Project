@extends('profile.layout')

@section('profile-content')
<div class="profile-content-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-lock"></i> Change Password
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('profile.update-password') }}" method="POST" class="needs-validation">
            @csrf

            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i>
                <strong>Password Requirements:</strong>
                <ul class="mb-0 mt-2">
                    <li>Minimum 8 characters</li>
                    <li>Must contain at least one uppercase letter</li>
                    <li>Must contain at least one lowercase letter</li>
                    <li>Must contain at least one number</li>
                    <li>Must contain at least one special character (@$!%*?&)</li>
                </ul>
            </div>

            <div class="mb-4">
                <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('current_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-lock"></i> Enter your current password for security
                </small>
                @error('current_password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <hr>

            <div class="mb-4">
                <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="passwordStrength" class="mt-2">
                    <small class="text-muted d-block">Password strength: <span id="strengthText">None</span></small>
                    <div class="progress" style="height: 5px;">
                        <div id="strengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password_confirmation', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="passwordMatch" class="mt-2" style="display: none;">
                    <small id="matchText"></small>
                </div>
                @error('password_confirmation')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <hr>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Password
                </button>
                <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, button) {
    const field = document.getElementById(fieldId);
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    
    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[@$!%*?&]/.test(password);
    const isLongEnough = password.length >= 8;
    
    if (hasLower) strength++;
    if (hasUpper) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;
    if (isLongEnough) strength++;
    
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const percentage = (strength / 5) * 100;
    
    strengthBar.style.width = percentage + '%';
    
    if (strength <= 1) {
        strengthText.textContent = 'Weak';
        strengthBar.className = 'progress-bar bg-danger';
    } else if (strength <= 2) {
        strengthText.textContent = 'Fair';
        strengthBar.className = 'progress-bar bg-warning';
    } else if (strength <= 3) {
        strengthText.textContent = 'Good';
        strengthBar.className = 'progress-bar bg-info';
    } else if (strength <= 4) {
        strengthText.textContent = 'Strong';
        strengthBar.className = 'progress-bar bg-success';
    } else {
        strengthText.textContent = 'Very Strong';
        strengthBar.className = 'progress-bar bg-success';
    }
});

// Password match checker
document.getElementById('password_confirmation').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    const matchText = document.getElementById('matchText');
    
    if (confirm) {
        matchDiv.style.display = 'block';
        if (password === confirm) {
            matchText.textContent = '✓ Passwords match';
            matchText.className = 'text-success';
        } else {
            matchText.textContent = '✗ Passwords do not match';
            matchText.className = 'text-danger';
        }
    } else {
        matchDiv.style.display = 'none';
    }
});
</script>

<style>
.input-group {
    border-radius: 8px;
    overflow: hidden;
}

.input-group .form-control {
    border-radius: 8px 0 0 8px;
}

.input-group .btn {
    border-radius: 0 8px 8px 0;
    border: 1px solid #ddd;
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

.alert {
    border-radius: 8px;
    border: none;
    background-color: #d1ecf1;
    color: #0c5460;
}

.alert ul {
    padding-left: 20px;
    margin-bottom: 0;
}

.alert ul li {
    margin-bottom: 4px;
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

.progress {
    border-radius: 4px;
    background-color: #e9ecef;
}

.progress-bar {
    transition: width 0.3s ease;
}

hr {
    margin: 2rem 0;
    border: none;
    border-top: 1px solid #eee;
}
</style>
@endsection
