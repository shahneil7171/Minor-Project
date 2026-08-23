<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $category->name }} | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #050a1a; color: #f8fafc; }
        .page { max-width: 1240px; margin: 0 auto; padding: 48px 20px 80px; }
        .crumbs { display: flex; gap: 10px; align-items: center; margin-bottom: 22px; color: #94a3b8; font-size: .9rem; flex-wrap: wrap; }
        .crumbs a { color: #93c5fd; text-decoration: none; font-weight: 600; }
        .crumbs a:hover { text-decoration: underline; }
        h1 { margin: 0 0 6px; font-size: clamp(1.8rem, 4vw, 2.6rem); letter-spacing: -0.02em; }
        .count { color: #94a3b8; margin: 0 0 30px; }
        .children { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
        .children a { padding: 7px 16px; border-radius: 999px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.05); color: #e2e8f0; text-decoration: none; font-weight: 600; font-size: .85rem; }
        .children a:hover { background: rgba(37,99,235,.25); }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .card { border-radius: 18px; overflow: hidden; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; transition: transform .2s ease, box-shadow .2s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 22px 44px rgba(0,0,0,0.32); }
        .card img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; background: #0d1428; }
        .cbody { padding: 16px; display: flex; flex-direction: column; flex: 1; gap: 4px; }
        .cbody h3 { margin: 0; font-size: 1.02rem; line-height: 1.3; }
        .cbody h3 a { color: inherit; text-decoration: none; }
        .cbody h3 a:hover { color: #93c5fd; }
        .brand { color: #94a3b8; font-size: .85rem; }
        .cat-badge { align-self: start; margin-top: 2px; padding: 3px 10px; border-radius: 999px; background: rgba(37,99,235,0.18); border: 1px solid rgba(59,130,246,0.35); color: #93c5fd; font-weight: 700; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; }
        .price { font-size: 1.2rem; font-weight: 800; margin-top: auto; padding-top: 10px; }
        .old { color: #64748b; text-decoration: line-through; font-size: .85rem; margin-left: 6px; }
        .actions { display: flex; gap: 8px; margin-top: 12px; }
        .btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; padding: 9px 10px; border-radius: 10px; font-weight: 700; font-size: .82rem; text-decoration: none; color: #fff; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; cursor: pointer; font-family: inherit; }
        .btn.outline { background: transparent; border: 1px solid rgba(255,255,255,.25); }
        .empty { padding: 46px; border-radius: 18px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); text-align: center; color: #cbd5e1; }
        @media (max-width: 980px) { .grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="crumbs">
            <a href="{{ route('home') }}">Home</a> <span>›</span>
            <span>{{ $category->name }}</span>
        </div>

        <h1>{{ $category->name }}</h1>
        <p class="count">{{ $productCount }} product{{ $productCount === 1 ? '' : 's' }} in this category</p>

        @if($category->children->isNotEmpty())
            <div class="children">
                @foreach($category->children as $child)
                    <a href="{{ route('categories.show', $child->slug) }}">{{ $child->name }}</a>
                @endforeach
            </div>
        @endif

        @if (!empty($products))
            <div class="grid">
                @foreach ($products as $slug => $product)
                    @php
                        $image = $product['image'] ?? '';
                        if (!empty($image) && strpos($image, 'http://') !== 0 && strpos($image, 'https://') !== 0) {
                            $image = asset(ltrim($image, '/'));
                        }
                    @endphp
                    <article class="card">
                        <img src="{{ $image }}" alt="{{ $product['title'] }}">
                        <div class="cbody">
                            <h3><a href="{{ route('product.show', ['product' => $slug]) }}">{{ $product['title'] }}</a></h3>
                            @if(!empty($product['brand']))
                                <span class="brand">{{ $product['brand'] }}</span>
                            @endif
                            @if(!empty($product['category']))
                                <span class="cat-badge">{{ $product['category'] }}</span>
                            @endif
                            <div class="price">
                                ${{ number_format((float) ($product['special_price'] > 0 && $product['special_price'] < $product['price'] ? $product['special_price'] : $product['price']), 2) }}
                                @if(!empty($product['special_price']) && $product['special_price'] < $product['price'])
                                    <span class="old">${{ number_format((float) $product['price'], 2) }}</span>
                                @endif
                            </div>
                            <div class="actions">
                                @auth
                                    @if(auth()->user()->account_type !== 'seller')
                                        <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}" style="margin:0;">
                                            @csrf
                                            <button type="submit" class="btn">Add to cart</button>
                                        </form>
                                    @else
                                        <span class="btn outline" style="cursor:default;">Seller account</span>
                                    @endif
                                    <a class="btn outline" href="{{ route('product.show', ['product' => $slug]) }}">View</a>
                                @else
                                    <a class="btn" href="{{ route('login') }}">Login to Buy</a>
                                    <a class="btn outline" href="{{ route('login') }}">View</a>
                                @endauth
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty">
                <h2 style="margin:0 0 8px; color:#f8fafc;">No products in {{ $category->name }} yet.</h2>
                <p style="margin:0;">Products assigned to this category will appear here automatically.</p>
            </div>
        @endif
    </div>
</body>
</html>
