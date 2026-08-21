<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') | KDP MART</title>
    <style>
        :root {
            --ka-bg: #080d1c;
            --ka-panel: #111827;
            --ka-border: #26304a;
            --ka-text: #e5e7eb;
            --ka-muted: #94a3b8;
            --ka-primary: #2563eb;
            --ka-success: #10b981;
            --ka-danger: #b91c1c;
            --ka-sidebar-w: 244px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: var(--ka-bg); color: var(--ka-text); min-height: 100vh; }

        /* ---------- Sidebar ---------- */
        .ka-sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: var(--ka-sidebar-w); background: #0b1120; border-right: 1px solid var(--ka-border); z-index: 40; display: flex; flex-direction: column; transition: transform .25s ease; }
        .ka-brand { display: flex; align-items: center; gap: 10px; padding: 18px 20px; border-bottom: 1px solid var(--ka-border); text-decoration: none; }
        .ka-brand .logo { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: .95rem; flex-shrink: 0; }
        .ka-brand span.name { color: #fff; font-weight: 800; letter-spacing: .03em; line-height: 1.15; }
        .ka-brand span.sub { display: block; font-size: .68rem; color: var(--ka-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .12em; }
        .ka-nav { flex: 1; overflow-y: auto; padding: 10px 0 24px; scrollbar-width: thin; scrollbar-color: #26304a transparent; }
        .ka-section-label { padding: 14px 20px 6px; font-size: .68rem; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; }
        .ka-link, .ka-toggle { display: flex; align-items: center; gap: 11px; width: calc(100% - 16px); margin: 2px 8px; padding: 10px 12px; border-radius: 9px; color: #cbd5e1; text-decoration: none; font-size: .92rem; font-weight: 600; border: none; background: none; cursor: pointer; text-align: left; }
        .ka-link:hover, .ka-toggle:hover { background: rgba(255,255,255,.05); color: #fff; }
        .ka-link.active { background: var(--ka-primary); color: #fff; }
        .ka-toggle .chev { margin-left: auto; transition: transform .2s ease; font-size: .7rem; color: var(--ka-muted); }
        .ka-group.open > .ka-toggle .chev { transform: rotate(180deg); }
        .ka-submenu { display: none; padding: 2px 0 6px; }
        .ka-group.open > .ka-submenu { display: block; }
        .ka-submenu a { display: flex; align-items: center; gap: 9px; margin: 1px 8px 1px 22px; padding: 8px 12px; border-radius: 8px; color: #94a3b8; text-decoration: none; font-size: .86rem; font-weight: 600; border-left: 2px solid var(--ka-border); }
        .ka-submenu a:hover { color: #fff; background: rgba(255,255,255,.04); }
        .ka-submenu a.active { color: #93c5fd; border-left-color: var(--ka-primary); background: rgba(37,99,235,.12); }

        /* ---------- Topbar & content ---------- */
        .ka-main { margin-left: var(--ka-sidebar-w); min-height: 100vh; display: flex; flex-direction: column; transition: margin-left .25s ease; }
        body.ka-collapsed .ka-sidebar { transform: translateX(-100%); }
        body.ka-collapsed .ka-main { margin-left: 0; }
        .ka-topbar { position: sticky; top: 0; z-index: 30; display: flex; align-items: center; gap: 14px; padding: 13px 22px; background: rgba(11,17,32,.94); border-bottom: 1px solid var(--ka-border); }
        .ka-burger { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 9px; border: 1px solid var(--ka-border); background: var(--ka-panel); color: var(--ka-text); cursor: pointer; font-size: 1rem; flex-shrink: 0; }
        .ka-page-title { font-size: 1.02rem; font-weight: 800; color: #fff; margin: 0; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ka-topbar-right { display: flex; align-items: center; gap: 10px; }
        .ka-store-link { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 9px; border: 1px solid var(--ka-border); background: var(--ka-panel); color: #cbd5e1; font-size: .82rem; font-weight: 700; text-decoration: none; }
        .ka-store-link:hover { color: #fff; border-color: #3b82f6; }
        .ka-account { position: relative; }
        .ka-account > button { display: inline-flex; align-items: center; gap: 9px; padding: 6px 12px 6px 6px; border-radius: 999px; border: 1px solid var(--ka-border); background: var(--ka-panel); color: var(--ka-text); cursor: pointer; font-weight: 700; font-size: .84rem; }
        .ka-account .avatar { width: 27px; height: 27px; border-radius: 50%; background: var(--ka-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 800; }
        .ka-account-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); min-width: 195px; background: var(--ka-panel); border: 1px solid var(--ka-border); border-radius: 12px; padding: 7px; box-shadow: 0 18px 40px rgba(0,0,0,.45); z-index: 45; }
        .ka-account.open .ka-account-menu { display: block; }
        .ka-account-menu .who { padding: 8px 10px 10px; border-bottom: 1px solid var(--ka-border); margin-bottom: 6px; }
        .ka-account-menu .who strong { display: block; color: #fff; font-size: .86rem; }
        .ka-account-menu .who small { color: var(--ka-muted); }
        .ka-account-menu button, .ka-account-menu a { display: flex; width: 100%; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 8px; border: none; background: none; color: #cbd5e1; font-size: .86rem; font-weight: 600; text-decoration: none; cursor: pointer; text-align: left; }
        .ka-account-menu button:hover, .ka-account-menu a:hover { background: rgba(255,255,255,.06); color: #fff; }
        .ka-content { padding: 24px 22px 60px; flex: 1; }
        .ka-flash { padding: 13px 16px; border-radius: 10px; margin-bottom: 18px; background: #064e3b; color: #d1fae5; font-weight: 600; }
        .ka-flash.error { background: #7f1d1d; color: #fecaca; }
        .ka-overlay { display: none; position: fixed; inset: 0; background: rgba(2,6,23,.65); z-index: 35; }
        body.ka-mobile-open .ka-overlay { display: block; }

        @media (max-width: 992px) {
            .ka-sidebar { transform: translateX(-100%); }
            body.ka-mobile-open .ka-sidebar { transform: translateX(0); box-shadow: 24px 0 60px rgba(0,0,0,.5); }
            .ka-main, body.ka-collapsed .ka-main { margin-left: 0; }
        }
    </style>
@stack('styles')
</head>
<body>
    <aside class="ka-sidebar" id="ka-sidebar">
        <a class="ka-brand" href="{{ route('admin.dashboard') }}">
            <span class="logo">KM</span>
            <span>
                <span class="name">{{ \App\Models\Setting::get('store_name') }}</span>
                <span class="sub">Admin Panel</span>
            </span>
        </a>
        <nav class="ka-nav">
            <a class="ka-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="fa fa-gauge-high"></i> Dashboard</a>

            <div class="ka-group {{ request()->routeIs('admin.categories.*', 'admin.manufacturers.*', 'admin.options.*', 'admin.reviews.*', 'products') ? 'open' : '' }}">
                <button type="button" class="ka-toggle" data-ka-group><i class="fa fa-cubes"></i> Catalog <span class="chev">▼</span></button>
                <div class="ka-submenu">
                    <a class="{{ request()->routeIs('products') ? 'active' : '' }}" href="{{ route('products') }}"><i class="fa fa-box"></i> Products</a>
                    <a class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="fa fa-sitemap"></i> Categories</a>
                    <a class="{{ request()->routeIs('admin.manufacturers.*') ? 'active' : '' }}" href="{{ route('admin.manufacturers.index') }}"><i class="fa fa-industry"></i> Manufacturers</a>
                    <a class="{{ request()->routeIs('admin.options.*') ? 'active' : '' }}" href="{{ route('admin.options.index') }}"><i class="fa fa-sliders"></i> Options</a>
                    <a class="{{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}"><i class="fa fa-star"></i> Reviews</a>
                </div>
            </div>

            <div class="ka-group {{ request()->routeIs('admin.orders.*', 'admin.customers.*', 'admin.returns.*', 'admin.coupons.*') ? 'open' : '' }}">
                <button type="button" class="ka-toggle" data-ka-group><i class="fa fa-cart-shopping"></i> Sales <span class="chev">▼</span></button>
                <div class="ka-submenu">
                    <a class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="fa fa-clipboard-list"></i> Orders</a>
                    <a class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}"><i class="fa fa-users"></i> Customers</a>
                    <a class="{{ request()->routeIs('admin.returns.*') ? 'active' : '' }}" href="{{ route('admin.returns.index') }}"><i class="fa fa-rotate-left"></i> Returns</a>
                    <a class="{{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><i class="fa fa-ticket"></i> Coupons</a>
                </div>
            </div>

            <div class="ka-group {{ request()->routeIs('admin.promotions.*', 'admin.newsletter.*', 'admin.coupons.*') ? 'open' : '' }}">
                <button type="button" class="ka-toggle" data-ka-group><i class="fa fa-bullhorn"></i> Marketing <span class="chev">▼</span></button>
                <div class="ka-submenu">
                    <a class="{{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><i class="fa fa-ticket"></i> Coupons</a>
                    <a class="{{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}" href="{{ route('admin.promotions.index') }}"><i class="fa fa-rectangle-ad"></i> Promotions</a>
                    <a class="{{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}" href="{{ route('admin.newsletter.index') }}"><i class="fa fa-envelope-open-text"></i> Newsletter</a>
                </div>
            </div>

            <div class="ka-group {{ request()->routeIs('admin.reports.*') ? 'open' : '' }}">
                <button type="button" class="ka-toggle" data-ka-group><i class="fa fa-chart-line"></i> Reports <span class="chev">▼</span></button>
                <div class="ka-submenu">
                    <a class="{{ request()->routeIs('admin.reports.sales') ? 'active' : '' }}" href="{{ route('admin.reports.sales') }}"><i class="fa fa-money-bill-trend-up"></i> Sales Reports</a>
                    <a class="{{ request()->routeIs('admin.reports.viewed') ? 'active' : '' }}" href="{{ route('admin.reports.viewed') }}"><i class="fa fa-eye"></i> Products Viewed</a>
                    <a class="{{ request()->routeIs('admin.reports.purchased') ? 'active' : '' }}" href="{{ route('admin.reports.purchased') }}"><i class="fa fa-bag-shopping"></i> Products Purchased</a>
                    <a class="{{ request()->routeIs('admin.reports.customers') ? 'active' : '' }}" href="{{ route('admin.reports.customers') }}"><i class="fa fa-user-chart"></i> Customer Reports</a>
                </div>
            </div>

            <div class="ka-group {{ request()->routeIs('admin.system.*', 'admin.settings.*', 'admin.backup.*') ? 'open' : '' }}">
                <button type="button" class="ka-toggle" data-ka-group><i class="fa fa-gear"></i> System <span class="chev">▼</span></button>
                <div class="ka-submenu">
                    <a class="{{ request()->routeIs('admin.system.users.*') ? 'active' : '' }}" href="{{ route('admin.system.users.index') }}"><i class="fa fa-user-shield"></i> Users</a>
                    <a class="{{ request()->routeIs('admin.system.groups.*') ? 'active' : '' }}" href="{{ route('admin.system.groups.index') }}"><i class="fa fa-user-group"></i> User Groups</a>
                    <a class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="fa fa-store"></i> Settings</a>
                    <a class="{{ request()->routeIs('admin.backup.*') ? 'active' : '' }}" href="{{ route('admin.backup.index') }}"><i class="fa fa-database"></i> Backup</a>
                </div>
            </div>
        </nav>
    </aside>
    <div class="ka-overlay" id="ka-overlay"></div>

    <div class="ka-main">
        <header class="ka-topbar">
            <button class="ka-burger" id="ka-burger" type="button" aria-label="Toggle sidebar">☰</button>
            <h1 class="ka-page-title">@yield('title', 'Admin Panel')</h1>
            <div class="ka-topbar-right">
                <a class="ka-store-link" href="{{ route('home') }}"><i class="fa fa-arrow-up-right-from-square"></i> View Store</a>
                <div class="ka-account" id="ka-account">
                    <button type="button" id="ka-account-btn">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                        {{ auth()->user()->name ?? 'Admin' }}
                    </button>
                    <div class="ka-account-menu">
                        <div class="who">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ auth()->user()->email }} · {{ ucfirst(auth()->user()->account_type) }}</small>
                        </div>
                        <a href="{{ route('home') }}"><i class="fa fa-store"></i> Visit storefront</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"><i class="fa fa-right-from-bracket"></i> Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="ka-content">
            @if (session('success'))
                <div class="ka-flash">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="ka-flash error">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    <script>
        (function () {
            var body = document.body;
            var burger = document.getElementById('ka-burger');
            var overlay = document.getElementById('ka-overlay');

            if (localStorage.getItem('ka-sidebar') === 'collapsed' && window.innerWidth > 992) {
                body.classList.add('ka-collapsed');
            }

            burger.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    body.classList.toggle('ka-mobile-open');
                } else {
                    body.classList.toggle('ka-collapsed');
                    localStorage.setItem('ka-sidebar', body.classList.contains('ka-collapsed') ? 'collapsed' : 'open');
                }
            });

            overlay.addEventListener('click', function () { body.classList.remove('ka-mobile-open'); });

            document.querySelectorAll('[data-ka-group]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    toggle.parentElement.classList.toggle('open');
                });
            });

            var account = document.getElementById('ka-account');
            document.getElementById('ka-account-btn').addEventListener('click', function (e) {
                e.stopPropagation();
                account.classList.toggle('open');
            });
            document.addEventListener('click', function (e) {
                if (! account.contains(e.target)) { account.classList.remove('open'); }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>