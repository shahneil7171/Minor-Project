<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>KDP MART | Premium Shopping</title>
        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                :root { color-scheme: dark; }
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    min-height: 100vh;
                    font-family: Inter, Arial, sans-serif;
                    background: linear-gradient(135deg, #020617 0%, #0f172a 35%, #1e3a8a 60%, #7f1d1d 100%);
                    color: #f8fafc;
                }
                body::before, body::after {
                    content: '';
                    position: fixed;
                    border-radius: 999px;
                    filter: blur(80px);
                    opacity: 0.35;
                    pointer-events: none;
                    animation: drift 15s ease-in-out infinite alternate;
                }
                body::before {
                    width: 360px;
                    height: 360px;
                    top: -70px;
                    left: -80px;
                    background: radial-gradient(circle, #60a5fa 0%, rgba(96,165,250,0) 70%);
                }
                body::after {
                    width: 400px;
                    height: 400px;
                    right: -90px;
                    bottom: -100px;
                    background: radial-gradient(circle, #f87171 0%, rgba(248,113,113,0) 70%);
                    animation-duration: 18s;
                }
                .page {
                    position: relative;
                    z-index: 1;
                    padding: 24px;
                }
                .shell {
                    max-width: 1240px;
                    margin: 0 auto;
                    background: rgba(2, 6, 23, 0.82);
                    border: 1px solid rgba(255,255,255,0.16);
                    border-radius: 28px;
                    box-shadow: 0 28px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                    backdrop-filter: blur(18px);
                }
                .navbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px 28px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    background: rgba(15, 23, 42, 0.55);
                }
                .brand {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-weight: 800;
                    letter-spacing: 0.02em;
                }
                .brand-badge {
                    width: 40px;
                    height: 40px;
                    border-radius: 12px;
                    display: grid;
                    place-items: center;
                    background: linear-gradient(135deg, #2563eb, #7f1d1d);
                    color: white;
                    font-weight: 700;
                }
                .nav-links {
                    display: flex;
                    gap: 18px;
                    color: #cbd5e1;
                    font-size: 0.95rem;
                }
                .nav-links a {
                    color: inherit;
                    text-decoration: none;
                }
                .nav-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    padding: 10px 14px;
                    border-radius: 999px;
                    text-decoration: none;
                    font-weight: 700;
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .btn:hover { transform: translateY(-1px); }
                .btn-primary {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: white;
                    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
                }
                .btn-dark {
                    background: rgba(255,255,255,0.08);
                    color: #f8fafc;
                    border: 1px solid rgba(255,255,255,0.12);
                }
                .hero {
                    display: grid;
                    grid-template-columns: 1.2fr 0.8fr;
                    gap: 24px;
                    padding: 34px 28px 28px;
                }
                .hero-card {
                    background: linear-gradient(135deg, rgba(30,58,138,0.72), rgba(127,29,29,0.55));
                    border: 1px solid rgba(255,255,255,0.12);
                    border-radius: 24px;
                    padding: 28px;
                    position: relative;
                    overflow: hidden;
                }
                .hero-card::before {
                    content: '';
                    position: absolute;
                    width: 220px;
                    height: 220px;
                    border: 1px solid rgba(255,255,255,0.18);
                    border-radius: 50%;
                    right: -40px;
                    top: -20px;
                }
                .eyebrow {
                    display: inline-block;
                    padding: 7px 12px;
                    border-radius: 999px;
                    background: rgba(255,255,255,0.12);
                    color: #bfdbfe;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.2em;
                    margin-bottom: 14px;
                }
                h1 { font-size: clamp(1.9rem, 3vw, 2.8rem); line-height: 1.15; margin: 0 0 12px; }
                .hero p { color: #dbeafe; line-height: 1.7; margin: 0 0 18px; }
                .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
                .mini-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 14px;
                    margin-top: 18px;
                }
                .mini-card {
                    border-radius: 16px;
                    padding: 16px;
                    background: rgba(15, 23, 42, 0.35);
                    border: 1px solid rgba(255,255,255,0.12);
                }
                .mini-card strong { display: block; font-size: 1.1rem; margin-bottom: 4px; }
                .product-card {
                    padding: 20px;
                    border-radius: 20px;
                    background: rgba(15, 23, 42, 0.78);
                    border: 1px solid rgba(255,255,255,0.12);
                }
                .product-card img {
                    width: 100%;
                    height: 140px;
                    object-fit: cover;
                    border-radius: 14px;
                    margin-bottom: 12px;
                    background: linear-gradient(135deg, #60a5fa, #f87171);
                }
                .section {
                    padding: 0 28px 28px;
                }
                .section-title {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 16px;
                }
                .chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 10px;
                    border-radius: 999px;
                    font-size: 0.85rem;
                    background: rgba(255,255,255,0.08);
                    color: #dbeafe;
                }
                .card-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 16px;
                }
                .product-meta { color: #94a3b8; font-size: 0.9rem; }
                .price { color: #f8fafc; font-weight: 800; margin-top: 10px; }
                .stats-row {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 14px;
                    padding: 0 28px 28px;
                }
                .stat-box {
                    border-radius: 18px;
                    padding: 18px;
                    background: rgba(15, 23, 42, 0.7);
                    border: 1px solid rgba(255,255,255,0.1);
                }
                .stat-box h3 { margin: 6px 0; font-size: 1.4rem; }
                .footer-note {
                    padding: 0 28px 28px;
                    color: #cbd5e1;
                    font-size: 0.95rem;
                }
                @keyframes drift {
                    from { transform: translate3d(0,0,0) scale(1); }
                    to { transform: translate3d(20px,-25px,0) scale(1.08); }
                }
                @media (max-width: 900px) {
                    .hero { grid-template-columns: 1fr; }
                    .card-grid { grid-template-columns: 1fr 1fr; }
                    .stats-row { grid-template-columns: 1fr 1fr; }
                }
                @media (max-width: 640px) {
                    .page { padding: 12px; }
                    .navbar, .hero, .section, .stats-row, .footer-note { padding-left: 16px; padding-right: 16px; }
                    .navbar { flex-direction: column; gap: 12px; align-items: flex-start; }
                    .nav-links { flex-wrap: wrap; }
                    .card-grid { grid-template-columns: 1fr; }
                    .stats-row { grid-template-columns: 1fr; }
                    .mini-grid { grid-template-columns: 1fr; }
                }
            </style>
        @endif
    </head>
    <body>
        <div class="page">
            <div class="shell">
                <header class="navbar">
                    <div class="brand">
                        <div class="brand-badge">K</div>
                        <span>KDP MART</span>
                    </div>
                    <nav class="nav-links">
                        <a href="#featured">Featured</a>
                        <a href="#collections">Collections</a>
                        <a href="#about">About</a>
                    </nav>
                    <div class="nav-actions">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="btn btn-primary">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-dark">Login</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </header>

                <section class="hero">
                    <div class="hero-card">
                        <span class="eyebrow">Premium shopping experience</span>
                        @auth
                            <div style="margin-bottom: 12px; padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.16); color: #eff6ff;">
                                <strong>Welcome back, {{ Auth::user()->name }}!</strong>
                            </div>
                        @endauth
                        <h1>Discover modern essentials for everyday luxury.</h1>
                        <p>From everyday essentials to premium picks, KDP MART brings sleek products, fast delivery, and a polished shopping experience to your doorstep.</p>
                        <div class="hero-actions">
                            <a href="#featured" class="btn btn-primary">Shop new arrivals</a>
                            <a href="{{ route('register') }}" class="btn btn-dark">Create account</a>
                        </div>
                        <div class="mini-grid">
                            <div class="mini-card">
                                <strong>Free shipping</strong>
                                <span>On orders above $80</span>
                            </div>
                            <div class="mini-card">
                                <strong>24/7 support</strong>
                                <span>Live help for every purchase</span>
                            </div>
                        </div>
                    </div>
                    <div class="product-card">
                        <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80" alt="Premium product">
                        <div class="chip">Trending this week</div>
                        <h3 style="margin: 12px 0 8px;">Signature Smart Watch</h3>
                        <p class="product-meta">Sleek metal finish • Premium health tracking • Fast charging</p>
                        <div class="price">$249</div>
                    </div>
                </section>

                <section class="stats-row">
                    <div class="stat-box">
                        <div class="chip">Customers</div>
                        <h3>120K+</h3>
                        <div class="product-meta">Happy shoppers worldwide</div>
                    </div>
                    <div class="stat-box">
                        <div class="chip">Delivery</div>
                        <h3>1-Day</h3>
                        <div class="product-meta">Express fulfillment</div>
                    </div>
                    <div class="stat-box">
                        <div class="chip">Reviews</div>
                        <h3>4.9/5</h3>
                        <div class="product-meta">Rated by real buyers</div>
                    </div>
                    <div class="stat-box">
                        <div class="chip">Support</div>
                        <h3>24/7</h3>
                        <div class="product-meta">Dedicated expert help</div>
                    </div>
                </section>

                <section class="section" id="featured">
                    <div class="section-title">
                        <h2 style="margin:0;">Featured products</h2>
                        <span class="chip">Curated for modern shoppers</span>
                    </div>
                    <div class="card-grid">
                        <div class="product-card">
                            <img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80" alt="Wireless headphones">
                            <h3>Studio Headphones</h3>
                            <p class="product-meta">Noise-cancelling • Immersive sound</p>
                            <div class="price">$179</div>
                        </div>
                        <div class="product-card">
                            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80" alt="Running shoes">
                            <h3>Performance Runner</h3>
                            <p class="product-meta">Lightweight • All-day comfort</p>
                            <div class="price">$129</div>
                        </div>
                        <div class="product-card">
                            <img src="https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80" alt="Premium bag">
                            <h3>Executive Tote</h3>
                            <p class="product-meta">Leather finish • Premium storage</p>
                            <div class="price">$159</div>
                        </div>
                    </div>
                </section>

                <section class="section" id="collections">
                    <div class="section-title">
                        <h2 style="margin:0;">Shop by collection</h2>
                        <span class="chip">Fresh arrivals every week</span>
                    </div>
                    <div class="card-grid">
                        <div class="product-card">
                            <h3>Smart Home</h3>
                            <p class="product-meta">Connected comfort and modern control.</p>
                        </div>
                        <div class="product-card">
                            <h3>Wellness</h3>
                            <p class="product-meta">Calm routines and everyday essentials.</p>
                        </div>
                        <div class="product-card">
                            <h3>Travel Gear</h3>
                            <p class="product-meta">Stylish carry-ons built for movement.</p>
                        </div>
                    </div>
                </section>

                <div class="footer-note" id="about">
                    KDP MART blends premium design, reliability, and seamless customer experience for a polished online shopping journey.
                </div>
            </div>
        </div>
    </body>
</html>
