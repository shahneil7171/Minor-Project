<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | E-Commerce</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
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
            max-width: 560px;
            border-radius: 24px;
            border: 1px solid #1f2937;
            background: #111827;
            box-shadow: 0 20px 45px rgba(0,0,0,0.35);
            padding: 40px;
        }
        .eyebrow { font-size: 0.8rem; letter-spacing: 0.3em; text-transform: uppercase; color: #fb923c; font-weight: 700; }
        h2 { margin: 10px 0 8px; font-size: 1.8rem; }
        .muted { color: #94a3b8; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; color: #e2e8f0; }
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
        .btn {
            width: 100%;
            padding: 13px 14px;
            border: none;
            border-radius: 12px;
            background: #f97316;
            color: white;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover { background: #ea580c; }
        .footer { text-align: center; margin-top: 18px; color: #94a3b8; font-size: 0.95rem; }
        .footer a { color: #fb923c; text-decoration: none; }
        .error-box, .success-box {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        .error-box {
            border: 1px solid rgba(248,113,113,0.4);
            background: rgba(248,113,113,0.12);
            color: #fecaca;
        }
        .success-box {
            border: 1px solid rgba(34,197,94,0.35);
            background: rgba(34,197,94,0.14);
            color: #bbf7d0;
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="eyebrow">Account recovery</p>
        <h2>Forgot your password?</h2>
        <p class="muted">Enter the email address for your account and we’ll send you a secure reset link.</p>

        @if (session('status'))
            <div class="success-box">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error-box">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="you@example.com">
            </div>
            <button type="submit" class="btn">Send reset link</button>
        </form>

        <p class="footer">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </div>
</body>
</html>
