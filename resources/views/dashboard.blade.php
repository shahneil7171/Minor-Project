<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #020617 0%, #0f3d91 32%, #2563eb 60%, #7f1d1d 100%);
            color: #f8fafc;
        }
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 999px;
            filter: blur(70px);
            opacity: 0.4;
            pointer-events: none;
            animation: drift 16s ease-in-out infinite alternate;
        }
        body::before {
            width: 280px;
            height: 280px;
            top: -40px;
            left: -30px;
            background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0) 70%);
        }
        body::after {
            width: 320px;
            height: 320px;
            right: -60px;
            bottom: -80px;
            background: radial-gradient(circle, #f87171 0%, rgba(248,113,113,0) 70%);
            animation-duration: 20s;
        }
        .panel {
            width: min(1040px, 100%);
            margin: 0 auto;
            border-radius: 24px;
            padding: 28px;
            background: linear-gradient(135deg, rgba(2,6,23,0.94), rgba(15,23,42,0.82));
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 30px 60px rgba(0,0,0,0.32);
            backdrop-filter: blur(18px);
            position: relative;
            z-index: 1;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            color: #bfdbfe;
            font-size: 0.9rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .stat-card {
            padding: 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .stat-card h3 { margin: 6px 0; font-size: 1.2rem; }
        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 16px;
        }
        .card {
            border-radius: 18px;
            padding: 18px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .list { display: grid; gap: 10px; }
        .list-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(15,23,42,0.42);
            color: #e2e8f0;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            color: white;
        }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .btn-danger { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
        .link-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .link-row a { color: #bfdbfe; text-decoration: none; }
        .link-row a:hover { color: white; }
        @keyframes drift {
            from { transform: translate3d(0,0,0) scale(1); }
            to { transform: translate3d(18px,-18px,0) scale(1.08); }
        }
        @media (max-width: 760px) {
            body { padding: 14px; }
            .panel { padding: 18px; }
            .stats-grid, .content-grid { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="topbar">
            <div>
                <div class="pill">Operations Center</div>
                <h1 style="margin: 10px 0 6px; font-size: 1.8rem;">Welcome back, {{ Auth::user()->name }}!</h1>
                <p style="margin:0; color:#cbd5e1;">Manage your account, orders, and shopping preferences from one polished workspace.</p>
            </div>
            <div class="pill">Premium account</div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="pill">Orders</div>
                <h3>3 active</h3>
                <div style="color:#cbd5e1;">Latest updates ready</div>
            </div>
            <div class="stat-card">
                <div class="pill">Profile</div>
                <h3>100% complete</h3>
                <div style="color:#cbd5e1;">Shipping details updated</div>
            </div>
            <div class="stat-card">
                <div class="pill">Rewards</div>
                <h3>1,250 pts</h3>
                <div style="color:#cbd5e1;">Eligible for exclusive offers</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3 style="margin-top:0;">Account overview</h3>
                <div class="list">
                    <div class="list-item"><strong>Name:</strong> {{ Auth::user()->name }}</div>
                    <div class="list-item"><strong>Email:</strong> {{ Auth::user()->email }}</div>
                    <div class="list-item"><strong>Status:</strong> Verified shopper</div>
                </div>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Quick actions</h3>
                <div class="list">
                    <div class="list-item">Track recent orders</div>
                    <div class="list-item">Update address details</div>
                    <div class="list-item">Explore new arrivals</div>
                </div>
            </div>
        </div>

        <div class="link-row">
            <a href="{{ route('products') }}">Shop products</a>
            <a href="{{ url('/') }}">Browse home</a>
        </div>

        <div class="actions">
            <a href="{{ route('products') }}" class="btn btn-primary">Browse products</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-danger">Log out</button>
            </form>
        </div>
    </div>
</body>
</html>
