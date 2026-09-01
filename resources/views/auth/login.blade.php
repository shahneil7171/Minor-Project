@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="container-fluid">
    <div class="auth-shell">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="auth-eyebrow">Welcome to KDP SMART MART</span>
                <h1 class="auth-title">Sign in to your account</h1>
                <p class="text-muted mb-0">Enter your email and password to get started.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </form>

            <p class="text-center text-muted mt-3 mb-0">
                Don’t have an account? <a href="{{ route('register') }}">Create one</a>
            </p>
        </div>
    </div>
</div>
@endsection

@section('extra-styles')
<style>
    .auth-shell {
        max-width: 480px;
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
