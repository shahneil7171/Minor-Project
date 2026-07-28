<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | E-Commerce</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background-color: #040d1c;
            background-image:
                radial-gradient(circle at 18% 12%, rgba(236,72,153,0.18), transparent 18%),
                radial-gradient(circle at 80% 22%, rgba(59,130,246,0.16), transparent 16%),
                radial-gradient(circle at 50% 90%, rgba(16,185,129,0.12), transparent 24%),
                linear-gradient(180deg, #030818 0%, #091d32 45%, #03070f 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 22% 18%, rgba(255,255,255,0.08), transparent 16%),
                radial-gradient(circle at 88% 15%, rgba(167,139,250,0.10), transparent 14%),
                radial-gradient(circle at 55% 80%, rgba(34,197,94,0.08), transparent 20%);
            pointer-events: none;
        }
        .card {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(56,189,248,0.18);
            background: rgba(8,15,31,0.92);
            backdrop-filter: blur(22px);
            box-shadow: 0 40px 110px rgba(0,0,0,0.55);
        }
        .hero {
            background: linear-gradient(135deg, rgba(79,70,229,0.96) 0%, rgba(14,165,233,0.90) 45%, rgba(52,211,153,0.92) 100%);
            color: #f8fafc;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 25% 30%, rgba(255,255,255,0.14), transparent 18%),
                radial-gradient(circle at 85% 20%, rgba(255,255,255,0.08), transparent 14%);
            pointer-events: none;
        }
        .hero h1 { font-size: 2.3rem; margin: 0 0 12px; line-height: 1.1; }
        .hero p { margin: 0; font-size: 1rem; line-height: 1.6; }
        .hero-badge {
            margin-top: 30px;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(17, 24, 39, 0.18);
            border-radius: 16px;
            padding: 16px;
            backdrop-filter: blur(8px);
            color: #111827;
        }
        .hero-badge strong {
            color: #111827;
        }
        .hero-badge p {
            color: #334155;
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
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.96);
            color: #f8fafc;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        input:focus { border-color: rgba(56,189,248,0.85); box-shadow: 0 0 0 4px rgba(56,189,248,0.18); background: rgba(15, 23, 42, 1); }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: rgba(255,255,255,0.05);
            color: #cbd5e1;
            cursor: pointer;
            padding: 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .toggle-password:hover { color: #f8fafc; background: rgba(148, 163, 184, 0.25); }
        .toggle-password svg { width: 18px; height: 18px; }
        .btn {
            width: 100%;
            padding: 13px 14px;
            border: none;
            border-radius: 12px;
            background: #f97316;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn:hover { background: #ea580c; }
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
    </style>
</head>
<body>
    <div class="card">
        <div class="hero">
            <div>
                <p>Open Source Store</p>
                <h1>Create your account</h1>
                <p>Join our marketplace and unlock faster checkout, special offers, and a personalized shopping experience.</p>
            </div>
            <div class="hero-badge">
                <strong>Already a member?</strong>
                <p>Sign in to continue shopping and manage your orders.</p>
            </div>
        </div>

        <div class="form-panel">
            <p class="eyebrow">New User</p>
            <h2>Create one</h2>
            <p class="muted">Fill in your details to get started.</p>

            @if ($errors->any())
                <div class="error-box">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.post') }}">
                @csrf
                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="John Doe">
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="you@example.com">
                </div>

                <div class="field">
                    <label>Role (optional)</label>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <label style="margin:0; font-weight:600;"><input type="radio" name="role" value="buyer" style="margin-right:8px;"> Buyer</label>
                        <label style="margin:0; font-weight:600;"><input type="radio" name="role" value="seller" style="margin-right:8px;"> Seller</label>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required placeholder="Enter at least 8 characters">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" data-target="password_confirmation" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn">Create account</button>
            </form>

            <p class="footer">
                Already have an account?
                <a href="{{ route('login') }}">Sign in</a>
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
