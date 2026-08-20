<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'E-Commerce') | KDP MART</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #11998e;
            --warning-color: #f5576c;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-bg: #f8f9fa;
            --dark-text: #333;
            --muted-text: #666;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            background-color: var(--light-bg);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--dark-text);
            line-height: 1.6;
        }

        /* ===== Header (OpenCart-inspired) ===== */
        .kdp-header {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Top strip */
        .kdp-topbar {
            background: rgba(0,0,0,0.18);
            border-bottom: 1px solid rgba(255,255,255,0.15);
            font-size: 0.8rem;
            padding: 6px 0;
            color: rgba(255,255,255,0.85);
        }

        .kdp-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .kdp-topbar a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .kdp-topbar a:hover {
            color: #fff;
        }

        .kdp-topbar-logout {
            background: transparent;
            border: none;
            padding: 0;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            font-size: 0.8rem;
            font-family: inherit;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .kdp-topbar-logout:hover {
            color: #fff;
        }

        .kdp-top-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .kdp-top-links .divider {
            color: rgba(255,255,255,0.4);
        }

        /* Main bar (logo + search + action icons) */
        .kdp-mainbar {
            padding: 0.9rem 0;
        }

        .kdp-mainbar-inner {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .kdp-logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.6rem;
            letter-spacing: 0.5px;
            color: #fff !important;
            text-decoration: none;
            white-space: nowrap;
        }

        .kdp-logo i {
            font-size: 1.4rem;
            color: #ffd54f;
        }

        .kdp-logo span {
            color: #ffd54f;
        }

        .kdp-logo:hover {
            color: #fff !important;
        }

        /* Search */
        .kdp-search {
            flex: 1 1 280px;
            max-width: 520px;
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .kdp-search input {
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            padding: 10px 16px;
            font-size: 0.9rem;
            color: var(--dark-text);
            background: transparent;
        }

        .kdp-search button {
            border: none;
            background: transparent;
            padding: 0 18px;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1rem;
        }

        .kdp-search button:hover {
            color: var(--secondary-color);
        }

        /* Action icons */
        .kdp-icons {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-left: auto;
        }

        .kdp-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            color: #fff !important;
            font-size: 1.05rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .kdp-icon:hover {
            background: rgba(255,255,255,0.3);
            color: #fff !important;
            transform: translateY(-2px);
        }

        .kdp-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 50%;
            background: var(--warning-color);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }

        /* Account avatar */
        .kdp-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .kdp-avatar:hover {
            background: rgba(255,255,255,0.35);
            border-color: #fff;
        }

        .kdp-role-switch {
            display: flex;
            gap: 6px;
            padding: 6px 12px;
        }

        .kdp-role-switch .btn {
            flex: 1;
            font-size: 0.8rem;
        }

        /* Bottom nav bar */
        .kdp-navbar {
            background: rgba(0,0,0,0.22);
            border-top: 1px solid rgba(255,255,255,0.12);
            padding: 0 !important;
        }

        .kdp-nav {
            gap: 4px;
        }

        .kdp-nav .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.88) !important;
            font-weight: 600;
            font-size: 0.92rem;
            padding: 12px 14px !important;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .kdp-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(255,255,255,0.12);
        }

        .kdp-nav .nav-link.active {
            color: #fff !important;
            border-bottom-color: #ffd54f;
            background: rgba(255,255,255,0.08);
        }

        .kdp-nav .fa-chevron-down {
            font-size: 0.7rem;
            transition: transform 0.3s ease;
        }

        .kdp-nav .dropdown:hover .fa-chevron-down {
            transform: rotate(180deg);
        }

        .kdp-nav .dropdown-toggle::after {
            display: none;
        }

        /* Nested submenu (desktop) */
        .kdp-nav .dropdown-submenu {
            position: relative;
        }

        .kdp-nav .dropdown-submenu .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -6px;
            margin-left: 1px;
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .kdp-nav .dropdown-submenu:hover > .dropdown-menu,
        .kdp-nav .dropdown-submenu:focus-within > .dropdown-menu {
            display: block;
        }

        .kdp-nav .submenu-arrow {
            float: right;
            font-size: 0.7rem;
            line-height: 1.4;
            margin-left: 8px;
        }

        .kdp-toggler {
            border-color: rgba(255,255,255,0.5) !important;
        }

        .kdp-toggler .navbar-toggler-icon {
            filter: invert(1) grayscale(100%);
        }

        /* Main Container */
        .main-content {
            min-height: 100vh;
            padding: 30px 0;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .card-header {
            background-color: var(--light-bg);
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0;
            padding: 20px;
            font-weight: 600;
            color: var(--dark-text);
        }

        .card-body {
            padding: 20px;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-secondary {
            color: #666;
            border: 2px solid #ddd;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #666;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        /* Forms */
        .form-label {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.875rem;
            display: block;
            margin-top: 5px;
        }

        .form-check-input {
            width: 1.25em;
            height: 1.25em;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            cursor: pointer;
            user-select: none;
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Badges */
        .badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge.bg-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #38ef7d 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #f093fb 100%) !important;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 0;
            margin-top: 60px;
            text-align: center;
        }

        .footer p {
            margin: 0;
        }

        /* Utilities */
        .text-primary {
            color: var(--primary-color) !important;
        }

        .text-muted {
            color: var(--muted-text) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .kdp-nav .nav-link {
                width: 100%;
                justify-content: flex-start;
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .kdp-nav .nav-link.active {
                border-bottom-color: transparent;
                border-left-color: #ffd54f;
            }

            .kdp-nav .dropdown-menu {
                position: static;
                box-shadow: none;
                margin-left: 12px;
                width: auto;
            }

            .kdp-nav .dropdown-submenu > .dropdown-menu {
                position: static;
                display: block;
                box-shadow: none;
                margin-left: 16px;
                padding: 0;
                border: none;
            }

            .kdp-nav .submenu-arrow {
                display: none;
            }
        }

        @media (max-width: 767.98px) {
            .main-content {
                padding: 15px 0;
            }

            .card-body, .card-header {
                padding: 15px;
            }

            .kdp-mainbar {
                padding: 0.6rem 0;
            }

            .kdp-mainbar-inner {
                gap: 0.75rem;
            }

            .kdp-logo {
                font-size: 1.3rem;
            }

            .kdp-search {
                order: 3;
                flex-basis: 100%;
                max-width: none;
            }

            .kdp-top-links {
                margin-left: auto;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: slideIn 0.3s ease;
        }

        /* Custom utility classes */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-lg-custom {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
    </style>

    @yield('extra-styles')
</head>
<body>
    <!-- Header -->
    <header class="kdp-header">
        <!-- Top strip -->
        <div class="kdp-topbar">
            <div class="container-fluid kdp-topbar-inner">
                <span><i class="fas fa-tag"></i> Welcome to KDP MART</span>
                <div class="kdp-top-links">
                    @auth
                        <a href="{{ route('profile.show') }}"><i class="fas fa-user-circle"></i> {{ auth()->user()->name }}</a>
                        <span class="divider">|</span>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="kdp-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <span class="divider">|</span>
                        <a href="{{ route('register') }}"><i class="fas fa-user-plus"></i> Register</a>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Main bar: logo + search + icons -->
        <div class="kdp-mainbar">
            <div class="container-fluid kdp-mainbar-inner">
                <a class="kdp-logo" href="{{ route('home') }}">
                    <i class="fas fa-shopping-bag"></i> KDP <span>MART</span>
                </a>

                <form class="kdp-search" action="{{ route('products') }}" method="GET">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search for products..." aria-label="Search products">
                    <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                </form>

                <div class="kdp-icons">
                    <a href="{{ route('wishlist.index') }}" class="kdp-icon" title="Wishlist" aria-label="Wishlist">
                        <i class="fas fa-heart"></i>
                        <span class="kdp-badge">{{ auth()->check() ? auth()->user()->wishlistItems()->count() : 0 }}</span>
                    </a>
                    <a href="{{ route('cart.index') }}" class="kdp-icon" title="Cart" aria-label="Cart">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="kdp-badge">{{ array_sum(array_column(session('cart', []), 'quantity')) }}</span>
                    </a>
                    @auth
                    <div class="kdp-account dropdown">
                        <button class="kdp-avatar" data-bs-toggle="dropdown" aria-expanded="false" title="My Account">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text fw-bold">{{ auth()->user()->name }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> My Dashboard</a></li>
                            <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="fas fa-user"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="fas fa-box-open"></i> My Orders</a></li>
                            <li><a class="dropdown-item" href="{{ route('profile.addresses.index') }}"><i class="fas fa-map-marker-alt"></i> Addresses</a></li>
                            <li><a class="dropdown-item" href="{{ route('profile.change-password') }}"><i class="fas fa-lock"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <div class="kdp-role-switch" role="group" aria-label="Account role">
                                    <a href="{{ route('role.set', ['role' => 'buyer']) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-user"></i> Buyer</a>
                                    <a href="{{ route('role.set', ['role' => 'seller']) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-store"></i> Seller</a>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    @endauth

                    <button class="navbar-toggler kdp-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kdpMainNav" aria-controls="kdpMainNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="kdp-navbar navbar navbar-expand-lg" aria-label="Main navigation">
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="kdpMainNav">
                    <ul class="navbar-nav kdp-nav">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home', 'dashboard') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">Categories <i class="fas fa-chevron-down"></i></a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('products') }}">
                                        <i class="fas fa-th"></i> All Categories
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @forelse($navCategories as $category)
                                    @if($category->children->isNotEmpty())
                                        <li class="dropdown-submenu">
                                            <a class="dropdown-item" href="#">
                                                {{ $category->name }}
                                                <i class="fas fa-chevron-right submenu-arrow"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                @foreach($category->children as $child)
                                                    <li><a class="dropdown-item" href="#">{{ $child->name }}</a></li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @else
                                        <li><a class="dropdown-item" href="#">{{ $category->name }}</a></li>
                                    @endif
                                @empty
                                    <li><a class="dropdown-item disabled" href="#" tabindex="-1">No categories yet</a></li>
                                @endforelse
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('products', 'product.show', 'products.*') ? 'active' : '' }}" href="{{ route('products') }}">Products</a>
                        </li>
                        <li class="nav-item">
                            <!-- Deals: no route yet, ready for later implementation -->
                            <a class="nav-link" href="#">Deals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Contact</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <p>&copy; 2024 KDP MART. All rights reserved. | <a href="#" style="color: white; text-decoration: none;">Privacy Policy</a> | <a href="#" style="color: white; text-decoration: none;">Terms of Service</a></p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>

    @yield('extra-scripts')
</body>
</html>
