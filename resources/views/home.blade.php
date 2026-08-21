<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDP MART | Shop Smart. Shop Better.</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; min-height: 100vh; background: #050a1a; color: #f8fafc; }

        /* Header */
        .header { position: sticky; top: 0; z-index: 50; background: rgba(5,10,26,0.82); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.08); }
        .header-inner { max-width: 1240px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
        .brand-badge { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; background: linear-gradient(135deg, #2563eb, #7f1d1d); color: white; }
        .brand-name { font-size: 1.25rem; font-weight: 800; letter-spacing: 0.02em; }
        .brand-name span { color: #93c5fd; }
        .nav { display: flex; gap: 6px; align-items: center; }
        .nav a { color: #cbd5e1; text-decoration: none; font-weight: 600; padding: 8px 12px; border-radius: 10px; transition: all .2s ease; }
        .nav a:hover { color: #fff; background: rgba(255,255,255,0.08); }
        .search { flex: 1; min-width: 200px; display: flex; align-items: center; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden; }
        .search input { flex: 1; background: transparent; border: none; outline: none; padding: 10px 16px; color: #f8fafc; font-size: 0.95rem; font-family: inherit; }
        .search input::placeholder { color: #64748b; }
        .search button { border: none; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 10px 16px; cursor: pointer; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .icons { display: flex; align-items: center; gap: 6px; }
        .icon { position: relative; display: inline-flex; align-items: center; gap: 6px; color: #e2e8f0; text-decoration: none; font-weight: 600; padding: 9px 12px; border-radius: 10px; transition: all .2s ease; }
        .icon:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .icon .label { font-size: 0.9rem; }
        .icon .badge { position: absolute; top: 2px; right: 2px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 999px; background: linear-gradient(135deg, #2563eb, #f43f5e); color: #fff; font-size: 0.7rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
        .hamburger { display: none; background: none; border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 8px 10px; cursor: pointer; font-size: 1.1rem; }
        /* Hero */
        .hero { position: relative; overflow: hidden; padding: 84px 24px; text-align: center; background: radial-gradient(circle at 20% 20%, rgba(37,99,235,0.28), transparent 45%), radial-gradient(circle at 80% 70%, rgba(127,29,29,0.32), transparent 45%), linear-gradient(135deg, #0b1226 0%, #101a35 55%, #1b1140 100%); }
        .hero::before, .hero::after { content: ''; position: absolute; border-radius: 999px; filter: blur(70px); opacity: 0.4; pointer-events: none; }
        .hero::before { width: 320px; height: 320px; top: -60px; left: -60px; background: radial-gradient(circle, #60a5fa, transparent 70%); }
        .hero::after { width: 360px; height: 360px; bottom: -100px; right: -80px; background: radial-gradient(circle, #f43f5e, transparent 70%); }
        .hero .chip { display: inline-block; padding: 6px 14px; border-radius: 999px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); color: #bfdbfe; font-weight: 600; font-size: 0.85rem; margin-bottom: 22px; }
        .hero h1 { margin: 0 0 16px; font-size: clamp(2.2rem, 6vw, 4rem); font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
        .hero h1 em { font-style: normal; background: linear-gradient(90deg, #60a5fa, #f43f5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero p { margin: 0 auto 30px; color: #cbd5e1; font-size: clamp(1rem, 2.4vw, 1.25rem); max-width: 560px; line-height: 1.6; }
        .hero-cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 30px; border-radius: 999px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; font-size: 1rem; transition: transform .2s ease, box-shadow .2s ease; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; box-shadow: 0 14px 34px rgba(37,99,235,0.35); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(37,99,235,0.45); }
        .btn-ghost { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        .btn-ghost:hover { background: rgba(255,255,255,0.14); transform: translateY(-2px); }

        /* Sections */
        .section { max-width: 1240px; margin: 0 auto; padding: 56px 20px 0; }
        .section-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 26px; flex-wrap: wrap; }
        .section-head h2 { margin: 0; font-size: 1.7rem; letter-spacing: -0.01em; }
        .section-head p { margin: 6px 0 0; color: #94a3b8; }
        .view-all { color: #93c5fd; text-decoration: none; font-weight: 700; white-space: nowrap; }
        .view-all:hover { text-decoration: underline; }

        /* Categories */
        .cats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; }
        .cat { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; text-decoration: none; color: inherit; padding: 24px 16px; border-radius: 18px; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .cat:hover { transform: translateY(-4px); border-color: rgba(96,165,250,0.5); box-shadow: 0 18px 40px rgba(0,0,0,0.3); }
        .cat .ico { width: 54px; height: 54px; border-radius: 14px; display: grid; place-items: center; background: linear-gradient(135deg, rgba(37,99,235,0.35), rgba(127,29,29,0.35)); border: 1px solid rgba(255,255,255,0.1); }
        .cat h3 { margin: 0; font-size: 1rem; }
        .cat small { color: #94a3b8; }
        /* Product grid */
        .product-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .pcard { position: relative; border-radius: 18px; overflow: hidden; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; transition: transform .2s ease, box-shadow .2s ease; }
        .pcard:hover { transform: translateY(-5px); box-shadow: 0 22px 44px rgba(0,0,0,0.32); }
        .pimg { position: relative; aspect-ratio: 1 / 1; overflow: hidden; background: #0d1428; display: block; }
        .pimg img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
        .pcard:hover .pimg img { transform: scale(1.06); }
        .offer-tag { position: absolute; top: 12px; left: 12px; z-index: 2; padding: 5px 10px; border-radius: 999px; background: linear-gradient(135deg, #f43f5e, #be123c); color: #fff; font-size: 0.72rem; font-weight: 800; }
        .heart { position: absolute; top: 10px; right: 10px; z-index: 3; width: 38px; height: 38px; border: none; border-radius: 12px; background: rgba(5,10,26,0.6); color: #fff; cursor: pointer; display: grid; place-items: center; backdrop-filter: blur(6px); transition: all .2s ease; }
        .heart:hover, .heart.active { color: #f43f5e; background: rgba(5,10,26,0.85); }
        .pbody { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .pbody h3 { margin: 0 0 6px; font-size: 1.02rem; line-height: 1.3; }
        .pbody h3 a { color: inherit; text-decoration: none; }
        .pbody h3 a:hover { color: #93c5fd; }
        .pbody .sub { margin: 0 0 12px; color: #94a3b8; font-size: 0.82rem; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .price-row { margin-top: auto; display: flex; align-items: baseline; gap: 8px; margin-bottom: 14px; }
        .price-row .price { font-size: 1.25rem; font-weight: 800; }
        .price-row .old { color: #64748b; text-decoration: line-through; font-size: 0.85rem; }
        .pactions { display: flex; gap: 10px; }
        .pactions form { flex: 1; margin: 0; }
        .mini-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 8px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; font-size: 0.85rem; text-decoration: none; color: #fff; background: linear-gradient(135deg, #2563eb, #1d4ed8); transition: all .2s ease; }
        .mini-btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .mini-btn.outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); }
        .mini-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        /* Trust / Footer / Flash */
        .trust { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 56px 20px 0; max-width: 1240px; }
        .trust-item { text-align: center; padding: 26px 16px; border-radius: 18px; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); }
        .trust-item h4 { margin: 0 0 6px; font-size: 1.05rem; }
        .trust-item p { margin: 0; color: #94a3b8; font-size: 0.9rem; }
        .footer { margin-top: 64px; padding: 34px 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.08); color: #94a3b8; }
        .footer .foot-links { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; margin-bottom: 12px; }
        .footer a { color: #93c5fd; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        .flash { position: fixed; top: 76px; left: 50%; transform: translateX(-50%); z-index: 60; max-width: 92vw; padding: 12px 22px; border-radius: 12px; font-weight: 600; background: rgba(5,10,26,0.9); border: 1px solid rgba(96,165,250,0.4); color: #bfdbfe; box-shadow: 0 12px 30px rgba(0,0,0,0.4); }

        /* Responsive */
        @media (max-width: 980px) {
            .cats-grid { grid-template-columns: repeat(3, 1fr); }
            .product-grid { grid-template-columns: repeat(2, 1fr); }
            .trust { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 720px) {
            .hamburger { display: block; }
            .nav, .icons { width: 100%; }
            .nav { display: none; flex-direction: column; align-items: stretch; order: 5; }
            .icons { display: none; flex-direction: column; align-items: stretch; order: 6; }
            .nav.open, .icons.open { display: flex; }
            .search { order: 4; min-width: 100%; }
            .header-inner { gap: 12px; }
            .brand-name { display: none; }
            .icon { justify-content: space-between; }
        }
        @media (max-width: 560px) {
            .cats-grid { grid-template-columns: repeat(2, 1fr); }
            .product-grid { grid-template-columns: 1fr; }
            .trust { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    <!-- ============ HEADER ============ -->
    <header class="header">
        <div class="header-inner">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-badge">KDP</span>
                <span class="brand-name">KDP <span>MART</span></span>
            </a>

            <button class="hamburger" id="hamburger" aria-label="Menu">☰</button>

            <nav class="nav" id="nav">
                <a href="{{ route('home') }}">Home</a>
                <a href="#categories">Categories</a>
                <a href="{{ route('products') }}">Products</a>
            </nav>

            <form class="search" method="GET" action="{{ route('products') }}">
                <input type="text" name="search" placeholder="Search for products, brands and more..." aria-label="Search">
                <button type="submit">🔍 Search</button>
            </form>

            <div class="icons" id="icons">
                @auth
                    <a class="icon" href="{{ route('profile.show') }}" title="My Profile">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="label">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </a>
                @else
                    <a class="icon" href="{{ route('login') }}" title="Login">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <span class="label">Login</span>
                    </a>
                @endauth

                <a class="icon" href="{{ route('wishlist.index') }}" title="Wishlist">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span class="label">Wishlist</span>
                    @if($wishlistCount > 0)<span class="badge">{{ $wishlistCount }}</span>@endif
                </a>

                <a class="icon" href="{{ route('cart.index') }}" title="Cart">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="label">Cart</span>
                    @if($cartCount > 0)<span class="badge">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>
    </header>

    <!-- ============ HERO ============ -->
    <section class="hero">
        <span class="chip">✦ Premium online shopping experience</span>
        <h1>Shop Smart. <em>Shop Better.</em></h1>
        <p>Discover products at the best prices. From the latest tech to everyday essentials — curated for modern shoppers.</p>
        <div class="hero-cta">
            <a href="{{ route('products') }}" class="btn btn-primary">Shop Now &nbsp;→</a>
            <a href="#featured" class="btn btn-ghost">Browse Best Sellers</a>
        </div>
    </section>

    <!-- ============ PROMOTIONAL BANNERS (admin Marketing module) ============ -->
    @if (!empty($promotions))
        <section class="section" id="promotions" aria-label="Current promotions">
            <div class="promo-banners">
                @foreach ($promotions as $promotion)
                    @php $target = $promotion->link ?: route('products'); @endphp
                    <a class="promo-banner" href="{{ $target }}" style="{{ $promotion->image ? 'background-image:url(' . $promotion->image . ')' : '' }}">
                        <span class="promo-title">{{ $promotion->title }}</span>
                        <span class="promo-cta">Shop now →</span>
                    </a>
                @endforeach
            </div>
        </section>
        <style>
            .promo-banners { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; max-width: 1100px; margin: 0 auto; padding: 0 20px; }
            .promo-banner { position: relative; display: flex; flex-direction: column; justify-content: center; gap: 6px; min-height: 130px; padding: 22px; border-radius: 18px; background: linear-gradient(135deg, #1d4ed8, #0b1120); background-size: cover; background-position: center; border: 1px solid rgba(255,255,255,0.16); text-decoration: none; overflow: hidden; }
            .promo-banner::before { content: ''; position: absolute; inset: 0; background: rgba(2,6,23,0.55); }
            .promo-banner > * { position: relative; z-index: 1; }
            .promo-title { color: #fff; font-size: 1.15rem; font-weight: 800; }
            .promo-cta { color: #93c5fd; font-weight: 700; font-size: .85rem; }
            .promo-banner:hover .promo-cta { color: #bfdbfe; }
        </style>
    @endif

    <!-- ============ CATEGORIES ============ -->
    <section class="section" id="categories">
        <div class="section-head">
            <div>
                <h2>Shop by Category</h2>
                <p>Find exactly what you're looking for.</p>
            </div>
            <a class="view-all" href="{{ route('products') }}">View all products →</a>
        </div>
        @php
            $categories = [
                ['name' => 'Electronics', 'icon' => '⚡', 'desc' => 'Latest gadgets'],
                ['name' => 'Mobiles', 'icon' => '📱', 'desc' => 'Smartphones & phones'],
                ['name' => 'Laptops', 'icon' => '💻', 'desc' => 'Work & play'],
                ['name' => 'Accessories', 'icon' => '🎧', 'desc' => 'Perfect add-ons'],
                ['name' => 'Fashion', 'icon' => '👕', 'desc' => 'Style essentials'],
                ['name' => 'Home & Kitchen', 'icon' => '🏠', 'desc' => 'Make it yours'],
            ];
        @endphp
        <div class="cats-grid">
            @foreach($categories as $cat)
                <a class="cat" href="{{ route('products', ['search' => $cat['name']]) }}">
                    <span class="ico" style="font-size:1.6rem;">{{ $cat['icon'] }}</span>
                    <h3>{{ $cat['name'] }}</h3>
                    <small>{{ $cat['desc'] }}</small>
                </a>
            @endforeach
        </div>
    </section>

    <!-- ============ PRODUCT SECTIONS ============ -->
    @php
        $isSeller = auth()->check() && auth()->user()->account_type === 'seller';
        $wishlistSlugs = $wishlistSlugs ?? [];
        $blocks = [
            'featured'      => ['title' => 'Featured Products', 'intro' => 'Curated picks our shoppers love.', 'items' => $featured,       'id' => 'featured', 'offer' => false],
            'bestSellers'   => ['title' => 'Best Sellers',       'intro' => 'The most popular products right now.', 'items' => $bestSellers, 'id' => 'best',  'offer' => false],
            'specialOffers' => ['title' => 'Special Offers',     'intro' => 'Great deals you don\'t want to miss.', 'items' => $specialOffers, 'id' => 'offers', 'offer' => true],
        ];
    @endphp

    @foreach($blocks as $block)
        <section class="section" id="{{ $block['id'] }}">
            <div class="section-head">
                <div>
                    <h2>{{ $block['title'] }}</h2>
                    <p>{{ $block['intro'] }}</p>
                </div>
                <a class="view-all" href="{{ route('products') }}">See more →</a>
            </div>

            @if(count($block['items']) > 0)
                <div class="product-grid">
                    @foreach($block['items'] as $slug => $product)
                        @php
                            $image = $product['image'] ?? '';
                            if (!empty($image) && strpos($image, 'http://') !== 0 && strpos($image, 'https://') !== 0) {
                                $image = asset(ltrim($image, '/'));
                            }
                            $inWishlist = in_array($slug, $wishlistSlugs);
                            $basePrice = (float) filter_var($product['price'] ?? '0', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                            $specialNum = isset($product['special_price']) && $product['special_price'] !== '' ? (float) filter_var($product['special_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
                            $hasSpecial = $specialNum > 0 && $specialNum < $basePrice;
                            $finalPrice = $hasSpecial ? $specialNum : $basePrice;
                        @endphp
                        <article class="pcard">
                            @if($block['offer'])
                                <span class="offer-tag">OFFER</span>
                            @endif

                            @auth
                                <form method="POST" action="{{ route('wishlist.toggle', ['product' => $slug]) }}">
                                    @csrf
                                    <button type="submit" class="heart {{ $inWishlist ? 'active' : '' }}" title="{{ $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                    </button>
                                </form>
                            @else
                                <a class="heart" href="{{ route('login') }}" title="Login to wishlist">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                </a>
                            @endauth

                            <a class="pimg" href="{{ route('product.show', ['product' => $slug]) }}">
                                <img src="{{ $image }}" alt="{{ $product['title'] ?? '' }}" loading="lazy">
                            </a>

                            <div class="pbody">
                                <h3><a href="{{ route('product.show', ['product' => $slug]) }}">{{ $product['title'] ?? '' }}</a></h3>
                                <p class="sub">{{ $product['subtitle'] ?? '' }}</p>
                                <div class="price-row">
                                    <span class="price">{{ '$' . number_format($finalPrice, 2) }}</span>
                                    @if($hasSpecial)
                                        <span class="old">${{ number_format($basePrice, 2) }}</span>
                                    @elseif($block['offer'])
                                        <span class="old">${{ number_format($basePrice * 1.25, 2) }}</span>
                                    @endif
                                </div>
                                <div class="pactions">
                                    @auth
                                        @if($isSeller)
                                            <a class="mini-btn outline" href="{{ route('product.show', ['product' => $slug]) }}">View</a>
                                            <span class="mini-btn" disabled title="Sellers can't purchase">Checkout</span>
                                        @else
                                            <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}">
                                                @csrf
                                                <button type="submit" class="mini-btn">🛒 Add</button>
                                            </form>
                                            <a class="mini-btn outline" href="{{ route('product.show', ['product' => $slug]) }}">View</a>
                                        @endif
                                    @else
                                        <a class="mini-btn" href="{{ route('login') }}">Login to Buy</a>
                                        <a class="mini-btn outline" href="{{ route('login') }}">View</a>
                                    @endauth
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p style="color:#94a3b8; text-align:center; padding:30px;">No products in this section yet. <a class="view-all" href="{{ route('products') }}">Browse the store →</a></p>
            @endif
        </section>
    @endforeach

    <!-- ============ TRUST ============ -->
    <div class="trust">
        <div class="trust-item"><h4>🚚 Free Delivery</h4><p>On orders above $49</p></div>
        <div class="trust-item"><h4>↩️ Easy Returns</h4><p>7-day hassle-free returns</p></div>
        <div class="trust-item"><h4>🔒 Secure Payments</h4><p>Your data stays protected</p></div>
        <div class="trust-item"><h4>💬 24/7 Support</h4><p>Dedicated expert help</p></div>
    </div>

    <!-- ============ FOOTER ============ -->
    <footer class="footer">
        <div class="foot-links">
            <a href="{{ route('home') }}">Home</a>
            <a href="#categories">Categories</a>
            <a href="{{ route('products') }}">Products</a>
            <a href="{{ route('wishlist.index') }}">Wishlist</a>
            <a href="{{ route('cart.index') }}">Cart</a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('orders.index') }}">My Orders</a>
                <a href="{{ route('profile.show') }}">Profile</a>
            @endauth
        </div>
        <p>© {{ date('Y') }} KDP MART. Shop Smart. Shop Better. All rights reserved.</p>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('hamburger')?.addEventListener('click', function () {
            document.getElementById('nav').classList.toggle('open');
            document.getElementById('icons').classList.toggle('open');
        });

        // Auto-hide flash messages
        setTimeout(function () {
            document.querySelectorAll('.flash').forEach(function (el) { el.style.display = 'none'; });
        }, 4000);
    </script>
</body>
</html>

