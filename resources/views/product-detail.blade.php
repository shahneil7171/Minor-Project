<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product['title'] }} | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%);
            color: #f8fafc;
            padding: 24px;
        }
        .container {
            max-width: 980px;
            margin: 0 auto;
            border-radius: 24px;
            padding: 24px;
            background: rgba(2,6,23,0.82);
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 24px 50px rgba(0,0,0,0.28);
        }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; }
        .topbar a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .detail-grid { display: grid; grid-template-columns: 1fr; gap: 24px; align-items: start; }
        .detail-card { display: none; }
        .detail-info { padding: 24px; }
        .detail-info h1 { margin: 0 0 12px; font-size: 2rem; }
        .detail-info p.lead { margin: 0 0 20px; color: #cbd5e1; line-height: 1.7; font-size: 1rem; }
        .detail-info .price { margin: 0 0 24px; font-size: 1.6rem; font-weight: 700; color: #fff; }
        .detail-info ul { padding-left: 20px; margin: 0 0 24px; color: #d1d5db; }
        .detail-info ul li { margin-bottom: 10px; }
        .qty-selector { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .qty-selector button { width: 40px; height: 40px; border: none; border-radius: 12px; background: #2563eb; color: white; font-size: 1.2rem; cursor: pointer; }
        .qty-selector span { min-width: 40px; text-align: center; font-weight: 700; color: #f8fafc; }
        .details-box { padding: 24px; border-radius: 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .details-box h2 { margin-top: 0; font-size: 1.2rem; color: #fff; }
        @media (max-width: 860px) { .detail-grid { grid-template-columns: 1fr; } .detail-card img { height: 300px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <p style="margin:0; color:#94a3b8;">Product details</p>
                <h2 style="margin:4px 0 0;">{{ $product['title'] }}</h2>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="{{ route('cart.index') }}">View cart</a>
                <a href="{{ route('products') }}">Back to products</a>
            </div>
        </div>
        @if (session('success'))
            <div style="margin-bottom: 20px; padding: 16px; border-radius: 12px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.4); color: #cffafe;">
                {{ session('success') }}
            </div>
        @endif
        @php $customProducts = $customProducts ?? []; @endphp
        <div class="detail-grid">
            <div class="detail-info">
                @php
                    $imageUrl = $product['image'] ?? '';
                    if (!empty($imageUrl) && strpos($imageUrl, 'http://') !== 0 && strpos($imageUrl, 'https://') !== 0 && strpos($imageUrl, 'data:') !== 0) {
                        $imageUrl = asset($imageUrl);
                    }
                @endphp
                @if (!empty($imageUrl))
                    <img src="{{ $imageUrl }}" alt="{{ $product['title'] }}" style="width:100%; height:auto; max-height:400px; object-fit:contain; background:rgba(255,255,255,0.04); border-radius:20px; margin-bottom:20px; border:1px solid rgba(255,255,255,0.14); padding:12px; display:block;">
                @endif
                <p class="lead">{{ $product['description'] }}</p>
                <p class="price">{{ $product['price'] }}</p>
                @if(session('role') === 'seller' && isset($customProducts[$slug]))
                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
                        <a href="{{ route('products.edit', ['product' => $slug]) }}" class="btn" style="background: linear-gradient(135deg, #f97316, #ea580c); padding:12px 18px; border-radius:12px; font-weight:700;">Edit Product</a>
                        <form method="POST" action="{{ route('products.destroy', ['product' => $slug]) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn" style="padding:12px 18px; background:#ef4444; color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Remove Product</button>
                        </form>
                    </div>
                @elseif(session('role') === 'admin')
                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
                        <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn" style="padding:12px 18px;">Add to cart</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}">
                        @csrf
                        <div class="qty-selector">
                            <button type="button" id="decreaseQty" aria-label="Decrease quantity">−</button>
                            <span id="quantityValue">1</span>
                            <button type="button" id="increaseQty" aria-label="Increase quantity">+</button>
                        </div>
                        <input type="hidden" name="quantity" id="quantityInput" value="1" />
                        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
                            <button type="submit" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border:none; border-radius:12px; font-weight:700; color:white; background:linear-gradient(135deg, #10b981, #059669); cursor:pointer;">Add to cart</button>
                            <button type="submit" formaction="{{ route('cart.buy-now', ['product' => $slug]) }}" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border:none; border-radius:12px; font-weight:700; color:white; background:linear-gradient(135deg, #2563eb, #1d4ed8); cursor:pointer;">Buy Now</button>
                        </div>
                    </form>
                @endif
                <h2 style="margin-bottom: 12px;">Features</h2>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const decreaseBtn = document.getElementById('decreaseQty');
                        const increaseBtn = document.getElementById('increaseQty');
                        const quantityValue = document.getElementById('quantityValue');
                        const quantityInput = document.getElementById('quantityInput');

                        let quantity = 1;

                        function updateQuantity() {
                            quantity = Math.max(1, quantity);
                            quantityValue.textContent = quantity;
                            quantityInput.value = quantity;
                        }

                        decreaseBtn.addEventListener('click', function () {
                            if (quantity > 1) {
                                quantity -= 1;
                                updateQuantity();
                            }
                        });

                        increaseBtn.addEventListener('click', function () {
                            quantity += 1;
                            updateQuantity();
                        });
                    });
                </script>
                <ul>
                    @foreach ($product['details'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <div class="details-box">
                    <h2>Why customers love it</h2>
                    <p style="margin: 0; color: #cbd5e1; line-height: 1.7;">This product combines premium design with real-world performance for a seamless shopping experience. It is ideal for users who want a refined, dependable item that feels as special as it performs.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
