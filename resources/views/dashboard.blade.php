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
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="pill">Premium account</div>
                <a href="{{ route('profile.show') }}" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(102,126,234,0.2); color: #bfdbfe; text-decoration: none; font-weight: 600; border: 1px solid rgba(102,126,234,0.4);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    My Profile
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="pill">Orders</div>
                <h3>3 active</h3>
                <div style="color:#cbd5e1;">Latest updates ready</div>
            </div>
            <div class="stat-card">
                <div class="pill">Profile</div>
                <h3>{{ Auth::user()->phone ? 'Complete' : 'Incomplete' }}</h3>
                <div style="color:#cbd5e1;">
                    @if(Auth::user()->phone)
                        All details updated
                    @else
                        <a href="{{ route('profile.edit') }}" style="color: #60a5fa; text-decoration: none;">Add phone number</a>
                    @endif
                </div>
            </div>
            <div class="stat-card">
                <div class="pill">Addresses</div>
                <h3>{{ Auth::user()->addresses()->count() }}</h3>
                <div style="color:#cbd5e1;">
                    <a href="{{ route('profile.addresses.index') }}" style="color: #60a5fa; text-decoration: none;">View all</a>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3 style="margin-top:0;">Account overview</h3>
                <div class="list">
                    <div class="list-item">
                        <strong>Name:</strong> 
                        <span style="float: right;">{{ Auth::user()->name }}</span>
                    </div>
                    <div class="list-item">
                        <strong>Email:</strong> 
                        <span style="float: right; font-size: 0.9rem;">{{ Auth::user()->email }}</span>
                    </div>
                    @if(Auth::user()->phone)
                        <div class="list-item">
                            <strong>Phone:</strong> 
                            <span style="float: right;">{{ Auth::user()->phone }}</span>
                        </div>
                    @endif
                    <div class="list-item">
                        <strong>Member since:</strong> 
                        <span style="float: right;">{{ Auth::user()->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Quick actions</h3>
                <div class="list">
                    <a href="{{ route('profile.show') }}" class="list-item" style="text-decoration: none; color: #e2e8f0; cursor: pointer;">
                        <svg width="16" height="16" style="display: inline; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        View profile
                    </a>
                    <a href="{{ route('profile.addresses.index') }}" class="list-item" style="text-decoration: none; color: #e2e8f0; cursor: pointer;">
                        <svg width="16" height="16" style="display: inline; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Manage addresses
                    </a>
                    <a href="{{ route('profile.change-password') }}" class="list-item" style="text-decoration: none; color: #e2e8f0; cursor: pointer;">
                        <svg width="16" height="16" style="display: inline; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Change password
                    </a>
                </div>
            </div>
        </div>

        <div class="link-row">
            <a href="{{ route('products') }}">Shop products</a>
            <a href="{{ route('profile.show') }}">My profile</a>
            <a href="{{ route('profile.addresses.index') }}">Saved addresses</a>
        </div>

        <div class="actions">
            <a href="{{ route('products') }}" class="btn btn-primary">Browse products</a>
            <a href="{{ route('profile.show') }}" class="btn btn-primary">My Account</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-danger">Log out</button>
            </form>
        </div>
    </div>
</body>
</html>
