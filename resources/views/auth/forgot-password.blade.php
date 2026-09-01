@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
<div class="container-fluid">
    <div class="auth-shell">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="auth-eyebrow">Password recovery</span>
                <h1 class="auth-title">Forgot password?</h1>
                <p class="text-muted mb-0">Enter your email and we will send you a 6-digit code to reset your password.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-info" role="alert">{{ session('status') }}</div>
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

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Send Password Reset Code</button>
            </form>

            <p class="text-center text-muted mt-3 mb-1">
                Already have a code? <a href="{{ route('password.verify') }}">Verify it here</a>
            </p>
            <p class="text-center text-muted mt-2 mb-0">
                Remembered your password? <a href="{{ route('login') }}">Sign in</a>
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
