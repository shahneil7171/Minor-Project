<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | E-Commerce</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #020617 0%, #0f3d91 32%, #2563eb 58%, #7f1d1d 100%);
            color: #f8fafc;
            padding: 24px;
        }
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 999px;
            filter: blur(70px);
            opacity: 0.45;
            pointer-events: none;
            animation: drift 16s ease-in-out infinite alternate;
        }
        body::before {
            width: 320px;
            height: 320px;
            top: -60px;
            left: -40px;
            background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0) 70%);
        }
        body::after {
            width: 360px;
            height: 360px;
            right: -70px;
            bottom: -90px;
            background: radial-gradient(circle, #f87171 0%, rgba(248,113,113,0) 70%);
            animation-duration: 20s;
        }
        .container {
            max-width: 980px;
            width: 100%;
            padding: 32px;
            background: linear-gradient(135deg, rgba(2, 6, 23, 0.94), rgba(15, 23, 42, 0.84));
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.32);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
        }
        .container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: linear-gradient(120deg, rgba(255,255,255,0.06), transparent 30%, rgba(255,255,255,0.04));
            pointer-events: none;
        }
        .welcome {
            font-size: 1.8rem;
            margin-bottom: 12px;
        }
        .subtitle {
            color: #cbd5e1;
            margin-bottom: 24px;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
        }
        .card {
            padding: 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 16px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .btn {
            display: inline-block;
            margin-top: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(37,99,235,0.26);
        }
        .btn.secondary {
            background: linear-gradient(135deg, #7f1d1d, #991b1b);
            box-shadow: 0 12px 24px rgba(127,29,29,0.25);
        }
        @keyframes drift {
            from { transform: translate3d(0,0,0) scale(1); }
            to { transform: translate3d(18px,-18px,0) scale(1.08); }
        }
        @media (max-width: 768px) {
            body { padding: 16px; }
            .container { padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="welcome">Dashboard</h1>
        <p class="subtitle">Welcome, {{ Auth::user()->name }}. This is your account dashboard.</p>

        <div class="card-grid">
            <div class="card">
                <strong>Profile</strong>
                <p>{{ Auth::user()->email }}</p>
            </div>
            <div class="card">
                <strong>Orders</strong>
                <p>No recent orders yet.</p>
            </div>
            <div class="card">
                <strong>Account</strong>
                <p>Your profile is ready for shopping.</p>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="{{ url('/') }}" class="btn">Back to Home</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn secondary">Log out</button>
            </form>
        </div>
    </div>
</body>
</html>
