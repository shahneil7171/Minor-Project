<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | E-Commerce</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #020617 0%, #111827 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid #1f2937;
            background: #111827;
            box-shadow: 0 20px 45px rgba(0,0,0,0.35);
            animation: loginSlideUp 1.3s cubic-bezier(.25,.8,.25,1) forwards;
        }
        .hero {
            background: linear-gradient(135deg, #f97316 0%, #f59e0b 50%, #fde68a 100%);
            color: #111827;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero h1 { font-size: 2.3rem; margin: 0 0 12px; line-height: 1.1; }
        .hero p { margin: 0; font-size: 1rem; line-height: 1.6; }
        .hero-badge {
            margin-top: 30px;
            background: rgba(255,255,255,0.65);
            border: 1px solid rgba(17, 24, 39, 0.18);
            border-radius: 16px;
            padding: 16px;
            backdrop-filter: blur(8px);
        }
       .hero-cta {
    display: inline-block;
    margin-top: 12px;
    background: #111827;
    color: #ffffff;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 10px;
    font-weight: 700;
    transition: all 0.3s ease;
}

.hero-cta:hover {
    background: #4232ec !important;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(249,115,22,0.45);
}
}

.hero-cta:active {
    transform: scale(0.95);
}
        .form-panel { padding: 40px; }
        .eyebrow { font-size: 0.8rem; letter-spacing: 0.3em; text-transform: uppercase; color: #fb923c; font-weight: 700; }
        .form-panel h2 { margin: 10px 0 8px; font-size: 1.8rem; }
        .form-panel .muted { color: #94a3b8; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; color: #e2e8f0; }
        .password-wrap {
            position: relative;
        }
        input {
            width: 100%;
            border: 1px solid #334155;
            background: #0f172a;
            color: #f8fafc;
            padding: 13px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
        }
        input:focus { border-color: #fb923c; box-shadow: 0 0 0 3px rgba(249,115,22,0.2); }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #cbd5e1;
            cursor: pointer;
            padding: 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover { color: #f8fafc; background: rgba(148, 163, 184, 0.15); }
        .toggle-password svg { width: 18px; height: 18px; }
        .row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #cbd5e1; margin-bottom: 18px; }
        .row a { color: #fb923c; text-decoration: none; }
        .btn {
    width: 100%;
    padding: 13px 14px;
    border: none;
    border-radius: 12px;
    background: #f97316;
    color: white;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(249,115,22,0.25);
}

.btn:hover {
    background: #4232ec;
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(249,115,22,0.45);
}

.btn:active {
    transform: scale(0.95);
}
        .footer { text-align: center; margin-top: 18px; color: #94a3b8; font-size: 0.95rem; }
        .footer a { color: #fb923c; text-decoration: none; }
        .error-box {
            margin-bottom: 16px;
            border: 1px solid rgba(248,113,113,0.4);
            background: rgba(248,113,113,0.12);
            color: #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        @media (max-width: 768px) {
            .card { grid-template-columns: 1fr; }
            .hero { padding: 32px; }
            .form-panel { padding: 32px; }
        }
        @keyframes loginSlideUp {
    from {
        opacity: 0;
        transform: translateY(100vh);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
    </style>
</head>
<body>
    <div class="card">
        <div class="hero">
            <div>
                <p>Open Source Store</p>
                <h1>Welcome back</h1>
                <p>Sign in to your account and continue shopping your favorite products with a faster checkout.</p>
            </div>
            <div class="hero-badge">
                <strong>New here?</strong>
                <p>Create an account to unlock deals and exclusive offers.</p>
                <a href="{{ route('register') }}" class="hero-cta">Create your account</a>
            </div>
        </div>

        <div class="form-panel">
            <p class="eyebrow">Secure Login</p>
            <h2>Sign in to your account</h2>
            <p class="muted">Enter your email and password to get started.</p>

            @if ($errors->any())
                <div class="error-box">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="you@example.com">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <label style="display:flex; align-items:center; gap:8px; margin:0;">
                        <input type="checkbox" name="remember" style="width:auto; padding:0; margin:0;">
                        Remember me
                    </label>
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                </div>

                <button type="submit" class="btn">Sign in</button>
            </form>

            <p class="footer">
                Don’t have an account?
                <a href="{{ route('register') }}">Create one</a>
            </p>
        </div>
    </div>

    <script>
        const eyeOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"></path><path d="M10.58 10.58A2 2 0 0 0 13.42 13.42"></path><path d="M9.88 5.08A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a18.18 18.18 0 0 1-3.1 4.02"></path><path d="M6.61 6.61A18.89 18.89 0 0 0 2 12s3.5 6 10 6a10.85 10.85 0 0 0 4.08-.78"></path></svg>';

        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                const showing = input.type === 'text';

                input.type = showing ? 'password' : 'text';
                this.innerHTML = showing ? eyeOpen : eyeClosed;
                this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    </script>
</body>
</html>
