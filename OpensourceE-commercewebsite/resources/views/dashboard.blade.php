<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | E-Commerce</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #111827;
        }
        .container {
            max-width: 960px;
            margin: 80px auto;
            padding: 32px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .welcome {
            font-size: 1.8rem;
            margin-bottom: 12px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .card {
            padding: 20px;
            background: #fff7ed;
            border: 1px solid #fdba74;
            border-radius: 14px;
        }
        .btn {
            display: inline-block;
            margin-top: 14px;
            background: #f97316;
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
        }
        .btn.secondary {
            background: #1f2937;
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
