@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="container-fluid">
    <div class="auth-shell">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="auth-eyebrow">Join KDP MART</span>
                <h1 class="auth-title">Create your account</h1>
                <p class="text-muted mb-0">Shop better with a free KDP MART account.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.post') }}">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="Jane Doe">
                    @error('name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <span class="form-label d-block">Account type</span>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="account_type" id="type-buyer" value="buyer" {{ old('account_type', 'buyer') === 'buyer' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="type-buyer">Buyer</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="account_type" id="type-seller" value="seller" {{ old('account_type') === 'seller' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="type-seller">Seller</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="account_type" id="type-delivery-partner" value="delivery_partner" {{ old('account_type') === 'delivery_partner' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="type-delivery-partner">Delivery Partner</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="account_type" id="type-staff" value="staff" {{ old('account_type') === 'staff' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="type-staff">Staff</label>
                        </div>
                    </div>
                    @error('account_type')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password" placeholder="Enter at least 8 characters">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">Minimum 8 characters with an uppercase letter, a lowercase letter, a number and a special character (@$!%*?&).</small>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">Confirm password</label>
                    <div class="password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control @error('password_confirmation') is-invalid @enderror" required autocomplete="new-password" placeholder="Confirm your password">
                        <button type="button" class="toggle-password" data-target="password_confirmation" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Create account</button>
            </form>

            <p class="text-center text-muted mt-3 mb-0">
                Already have an account? <a href="{{ route('login') }}">Sign in</a>
            </p>
        </div>
    </div>
</div>
@endsection
@section('extra-styles')
<style>
    .auth-shell {
        max-width: 520px;
        margin: 0 auto;
    }

    .auth-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        padding: 30px;
    }

    .auth-eyebrow {
        display: inline-block;
        font-size: .78rem;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: var(--primary-color);
        font-weight: 700;
    }

    .auth-title {
        font-size: 1.55rem;
        font-weight: 700;
        margin: 6px 0 4px;
    }

    .password-wrap {
        position: relative;
    }

    .password-wrap .form-control {
        padding-right: 42px;
    }

    .password-wrap .toggle-password {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: var(--muted-text);
        padding: 6px 8px;
        line-height: 1;
        border-radius: 6px;
        cursor: pointer;
    }

    .password-wrap .toggle-password:hover,
    .password-wrap .toggle-password:focus-visible {
        color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
    }

    .auth-card a {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
    }

    .auth-card a:hover {
        text-decoration: underline;
    }

    @media (max-width: 767.98px) {
        .auth-card {
            padding: 20px;
        }
    }
</style>
@endsection

@section('extra-scripts')
<script>
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(this.dataset.target);
            var showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';

            var icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', showing);
                icon.classList.toggle('fa-eye-slash', !showing);
            }

            this.setAttribute('aria-pressed', showing ? 'false' : 'true');
            this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });
</script>
@endsection
