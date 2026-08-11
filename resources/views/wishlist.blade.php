<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 1040px; margin: 0 auto; border-radius: 24px; padding: 26px; background: rgba(2,6,23,0.85); border: 1px solid rgba(255,255,255,0.16); box-shadow: 0 24px 50px rgba(0,0,0,0.28); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 6px; font-size: 1.8rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header .links { display: flex; gap: 12px; flex-wrap: wrap; }
        .header .links a, .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .btn.green { background: linear-gradient(135deg, #10b981, #059669); }
        .btn.red { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
        .btn.ghost { background: transparent; border: 1px solid rgba(255,255,255,0.2); }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; background: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.4); color: #cffafe; font-weight: 600; }
        .flash.error { background: rgba(248,113,113,0.12); border-color: rgba(248,113,113,0.4); color: #fecaca; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .card { border-radius: 18px; overflow: hidden; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); display: flex; flex-direction: column; }
        .card img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; }
        .body { padding: 16px; display: flex; flex-direction: column; flex: 1; }
        .body h3 { margin: 0 0 6px; font-size: 1.05rem; }
        .body .sub { margin: 0 0 10px; color: #94a3b8; font-size: 0.85rem; line-height: 1.45; }
        .body .price { font-size: 1.2rem; font-weight: 800; margin: auto 0 14px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .actions form { margin: 0; flex: 1; }
        .empty { padding: 52px; text-align: center; border-radius: 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: #cbd5e1; }
        .empty h2 { color: #f8fafc; margin: 0 0 8px; }
        @media (max-width: 820px) { .grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>❤️ My Wishlist</h1>
                <p>Products you've saved for later.</p>
            </div>
            <div class="links">
                <a href="{{ route('home') }}">🏠 Store Home</a>
                <a href="{{ route('products') }}">Browse Products</a>
                <a href="{{ route('cart.index') }}">🛒 Cart</a>
            </div>
        </div>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        @if (count($items) > 0)
            <div class="grid">
                @foreach($items as $slug => $product)
                    @php
                        $image = $product['image'] ?? '';
                        if (!empty($image) && strpos($image, 'http://') !== 0 && strpos($image, 'https://') !== 0) {
                            $image = asset(ltrim($image, '/'));
                        }
                    @endphp
                    <div class="card">
                        <a href="{{ route('product.show', ['product' => $slug]) }}" style="text-decoration:none;color:inherit;">
                            <img src="{{ $image }}" alt="{{ $product['title'] ?? '' }}">
                        </a>
                        <div class="body">
                            <h3><a href="{{ route('product.show', ['product' => $slug]) }}" style="color:inherit;text-decoration:none;">{{ $product['title'] ?? '' }}</a></h3>
                            <p class="sub">{{ $product['subtitle'] ?? '' }}</p>
                            <div class="price">{{ $product['price'] ?? '' }}</div>
                            <div class="actions">
                                @if(auth()->user()->account_type === 'seller')
                                    <a class="btn" href="{{ route('product.show', ['product' => $slug]) }}">View</a>
                                @else
                                    <form method="POST" action="{{ route('wishlist.to-cart', ['product' => $slug]) }}">
                                        @csrf
                                        <button type="submit" class="btn green" style="width:100%;border:none;cursor:pointer;">Move to Cart</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('wishlist.remove', ['product' => $slug]) }}">
                                    @csrf
                                    <button type="submit" class="btn red" style="width:100%;border:none;cursor:pointer;">Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">
                <h2>Your wishlist is empty.</h2>
                <p>Tap the heart on any product to save it here.</p>
                <div style="margin-top:20px;">
                    <a class="btn green" href="{{ route('products') }}">Browse Products</a>
                </div>
            </div>
        @endif
    </div>

    <script>
        setTimeout(function () {
            document.querySelectorAll('.flash').forEach(function (el) { el.style.display = 'none'; });
        }, 4000);
    </script>
</body>
</html>

