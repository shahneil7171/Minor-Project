<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deals | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #050a1a; color: #f8fafc; }
        .page { max-width: 1240px; margin: 0 auto; padding: 48px 20px 80px; }
        .back { display: inline-flex; align-items: center; gap: 8px; color: #93c5fd; text-decoration: none; font-weight: 700; margin-bottom: 26px; }
        .back:hover { text-decoration: underline; }
        h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.6rem); letter-spacing: -0.02em; }
        h1 em { font-style: normal; background: linear-gradient(90deg, #60a5fa, #f43f5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .lead { margin: 0 0 34px; color: #94a3b8; max-width: 640px; line-height: 1.6; }
        .deal-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .deal { position: relative; border-radius: 18px; overflow: hidden; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; transition: transform .2s ease, box-shadow .2s ease; }
        .deal:hover { transform: translateY(-5px); box-shadow: 0 22px 44px rgba(0,0,0,0.32); }
        .deal img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; background: #0d1428; }
        .tag { position: absolute; top: 12px; left: 12px; padding: 5px 12px; border-radius: 999px; background: linear-gradient(135deg, #f43f5e, #be123c); color: #fff; font-weight: 800; font-size: .78rem; letter-spacing: .04em; }
        .dbody { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .dbody h3 { margin: 0 0 6px; font-size: 1.02rem; line-height: 1.3; }
        .dbody h3 a { color: inherit; text-decoration: none; }
        .dbody h3 a:hover { color: #93c5fd; }
        .prices { margin-top: auto; display: flex; align-items: baseline; gap: 8px; margin-bottom: 14px; }
        .price { font-size: 1.25rem; font-weight: 800; }
        .old { color: #64748b; text-decoration: line-through; font-size: .85rem; }
        .view-btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 14px; border-radius: 10px; font-weight: 700; font-size: .85rem; text-decoration: none; color: #fff; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .view-btn:hover { opacity: .92; }
        .empty { padding: 46px; border-radius: 18px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); text-align: center; color: #cbd5e1; }
        @media (max-width: 980px) { .deal-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .deal-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page">
        <a class="back" href="{{ route('home') }}">← Back to store</a>
        <h1>Hot <em>Deals</em></h1>
        <p class="lead">Real discounts from our live catalog — products with an active special price, biggest savings first.</p>

        @if (!empty($deals))
            <div class="deal-grid">
                @foreach ($deals as $slug => $product)
                    @php
                        $image = $product['image'] ?? '';
                        if (!empty($image) && strpos($image, 'http://') !== 0 && strpos($image, 'https://') !== 0) {
                            $image = asset(ltrim($image, '/'));
                        }
                    @endphp
                    <article class="deal">
                        <span class="tag">-{{ $product['discount_percent'] }}%</span>
                        <img src="{{ $image }}" alt="{{ $product['title'] ?? 'Product' }}">
                        <div class="dbody">
                            <h3><a href="{{ route('product.show', ['product' => $slug]) }}">{{ $product['title'] ?? '' }}</a></h3>
                            @if(!empty($product['category']))
                                <span style="align-self:flex-start; margin:2px 0 4px; padding:3px 10px; border-radius:999px; font-weight:800; font-size:0.68rem; letter-spacing:.06em; text-transform:uppercase; background:rgba(37,99,235,0.25); border:1px solid rgba(59,130,246,0.4); color:#93c5fd;">{{ $product['category'] }}</span>
                            @endif
                            <div class="prices">
                                <span class="price">${{ number_format((float) $product['deal_price'], 2) }}</span>
                                <span class="old">${{ number_format((float) $product['base_price'], 2) }}</span>
                            </div>
                            @auth
                                <a class="view-btn" href="{{ route('product.show', ['product' => $slug]) }}">View deal</a>
                            @else
                                <a class="view-btn" href="{{ route('login') }}">Login to view</a>
                            @endauth
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty">
                <h2 style="margin:0 0 8px; color:#f8fafc;">No active deals right now.</h2>
                <p style="margin:0;">Check back soon — special offers appear here automatically when admins set special prices.</p>
            </div>
        @endif
    </div>
</body>
</html>
