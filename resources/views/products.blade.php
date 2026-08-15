<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | KDP MART</title>
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
            max-width: 1100px;
            margin: 0 auto;
            border-radius: 24px;
            padding: 24px;
            background: rgba(2,6,23,0.82);
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 24px 50px rgba(0,0,0,0.28);
        }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .card { padding: 24px 20px; border-radius: 16px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); transition: transform .2s ease, box-shadow .2s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 18px 40px rgba(0,0,0,0.24); }
        .card h3 { margin: 0 0 10px; font-size: 1.2rem; }
        .card p { margin: 0; color: #cbd5e1; line-height: 1.5; }
        .card-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .card-link { display: block; color: inherit; text-decoration: none; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .btn.buy-now { background: linear-gradient(135deg, #10b981, #059669); }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .header { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <div class="container">
        @php
            $customProducts = $customProducts ?? [];
            $parentCats = collect($categories ?? [])->whereNull('parent_id');
            $fmt = function ($n) { return '$' . number_format((float) $n, 2); };
            $category = $category ?? '';
        @endphp
        <div class="header">
            <div>
                <h1 style="margin:0 0 6px;">Featured products</h1>
                <p style="margin:0; color:#cbd5e1;">A polished storefront experience for authenticated shoppers.</p>
            </div>
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                @if(auth()->user()->account_type === 'seller' || auth()->user()->account_type === 'admin')
                    <a href="{{ route('products.create') }}" class="btn" style="background: linear-gradient(135deg, #10b981, #059669);">Add product</a>
                    <a href="{{ route('home') }}" class="btn">Back to store</a>
                @else
                    <a href="{{ route('cart.index') }}" class="btn">View cart</a>
                    <a href="{{ route('home') }}" class="btn">Back to store</a>
                @endif
            </div>
        </div>

        <!-- Search and Sort Bar -->
        <div style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; align-items: center;">
            <form method="GET" action="{{ route('products') }}" style="display: flex; gap: 10px; flex: 1; min-width: 250px;">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search products..." 
                    value="{{ $search ?? '' }}"
                    style="
                        flex: 1;
                        padding: 12px 16px;
                        border-radius: 10px;
                        background: rgba(255,255,255,0.08);
                        border: 1px solid rgba(255,255,255,0.2);
                        color: #f8fafc;
                        font-size: 1rem;
                    "
                />
                <button 
                    type="submit" 
                    class="btn"
                    style="background: linear-gradient(135deg, #2563eb, #1d4ed8); padding: 12px 20px;"
                >
                    Search
                </button>
                <select name="category" onchange="this.form.submit()" style="
                    padding: 12px 16px;
                    border-radius: 10px;
                    background: rgba(255,255,255,0.08);
                    border: 1px solid rgba(255,255,255,0.2);
                    color: #f8fafc;
                    font-size: 1rem;
                    cursor: pointer;
                    min-width: 160px;
                ">
                    <option value="" {{ empty($category ?? '') ? 'selected' : '' }}>All categories</option>
                    @foreach($parentCats as $cat)
                        <option value="{{ $cat->name }}" {{ ($category ?? '') === $cat->name ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @foreach($cat->children as $child)
                            <option value="{{ $child->name }}" {{ ($category ?? '') === $child->name ? 'selected' : '' }}>&nbsp;&nbsp;— {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>

                @if(!empty($search))
                    <a 
                        href="{{ route('products') }}" 
                        class="btn"
                        style="background: rgba(255,255,255,0.1); padding: 12px 20px;"
                    >
                        Clear
                    </a>
                @endif
            </form>

            <select 
                name="sort" 
                id="sort-select"
                style="
                    padding: 12px 16px;
                    border-radius: 10px;
                    background: rgba(255,255,255,0.08);
                    border: 1px solid rgba(255,255,255,0.2);
                    color: #f8fafc;
                    font-size: 1rem;
                    cursor: pointer;
                    min-width: 180px;
                    appearance: none;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23f8fafc" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>');
                    background-repeat: no-repeat;
                    background-position: right 10px center;
                    background-size: 20px;
                    padding-right: 36px;
                "
            >
                <option value="none" {{ ($sort ?? 'none') === 'none' ? 'selected' : '' }}>Sort by</option>
                <option value="price-asc" {{ ($sort ?? '') === 'price-asc' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price-desc" {{ ($sort ?? '') === 'price-desc' ? 'selected' : '' }}>Price: High to Low</option>
            </select>

            <style>
                #sort-select option {
                    background: #1a202c;
                    color: #f8fafc;
                    padding: 10px;
                }
                #sort-select option:hover {
                    background: #2563eb;
                    color: white;
                }
                #sort-select option:checked {
                    background: #2563eb;
                    color: white;
                }
            </style>
        </div>

        @php
            $customProducts = $customProducts ?? [];
            $parentCats = collect($categories ?? [])->whereNull('parent_id');
            $fmt = function ($n) { return '$' . number_format((float) $n, 2); };
        @endphp
        <div class="grid">
            @if(count($products) > 0)
                @foreach ($products as $slug => $product)
                    @php
                        $imageUrl = $product['image'] ?? '';
                        if (!empty($imageUrl) && strpos($imageUrl, 'http://') !== 0 && strpos($imageUrl, 'https://') !== 0 && strpos($imageUrl, 'data:') !== 0) {
                            $imageUrl = asset($imageUrl);
                        }
                        $pfloat = function ($p) { return (float) filter_var((string) $p, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); };
                        $basePrice = $pfloat($product['price'] ?? 0);
                        $specialNum = isset($product['special_price']) && $product['special_price'] !== '' ? $pfloat($product['special_price']) : 0;
                        $hasSpecial = $specialNum > 0 && $specialNum < $basePrice;
                        $finalPrice = $hasSpecial ? $specialNum : $basePrice;
                        $brand = $product['brand'] ?? '';
                        $categoryName = $product['category'] ?? '';
                        $stockStatus = $product['stock_status'] ?? 'in-stock';
                    @endphp
                    <div class="card">
                        @if (!empty($imageUrl))
                            <img src="{{ $imageUrl }}" alt="{{ $product['title'] }}" style="width:100%; height:200px; object-fit:contain; background:rgba(255,255,255,0.04); border-radius:12px; margin-bottom:12px; border:1px solid rgba(255,255,255,0.12); padding:8px; display:block;">
                        @endif
                        <h3>{{ $product['title'] }}</h3>
                        @if(!empty($brand))
                            <p style="margin:0 0 4px; font-size:0.85rem; color:#93c5fd; font-weight:700;">{{ $brand }}</p>
                        @endif
                        <p>{{ $product['subtitle'] }}</p>
                        <div style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin:8px 0;">
                            <span style="color:#34d399; font-weight:800; font-size:1.1rem;">{{ $fmt($finalPrice) }}</span>
                            @if($hasSpecial)<s style="color:#94a3b8;">{{ $fmt($basePrice) }}</s>@endif
                            @if($stockStatus === 'in-stock')
                                <span style="font-size:0.7rem; font-weight:800; padding:3px 8px; border-radius:999px; background:rgba(16,185,129,0.16); color:#34d399;">In Stock</span>
                            @elseif($stockStatus === 'pre-order')
                                <span style="font-size:0.7rem; font-weight:800; padding:3px 8px; border-radius:999px; background:rgba(245,158,11,0.16); color:#fbbf24;">Pre-Order</span>
                            @else
                                <span style="font-size:0.7rem; font-weight:800; padding:3px 8px; border-radius:999px; background:rgba(239,68,68,0.16); color:#f87171;">Out of Stock</span>
                            @endif
                        </div>
                        <div class="card-actions">
                            <a class="btn" href="{{ route('product.show', ['product' => $slug]) }}">Details</a>
                            @if(auth()->user()->account_type === 'seller')
                                <a class="btn" href="{{ route('products.edit', ['product' => $slug]) }}" style="background: linear-gradient(135deg, #f97316, #ea580c);">Edit</a>
                                @if(isset($customProducts[$slug]))
                                    <form method="POST" action="{{ route('products.destroy', ['product' => $slug]) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="btn" style="background: #ef4444;" onclick="return confirm('Are you sure?')">Remove</button>
                                    </form>
                                @endif
                            @elseif(auth()->user()->account_type === 'admin')
                                <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn">Add to cart</button>
                                </form>
                                <form method="POST" action="{{ route('cart.buy-now', ['product' => $slug]) }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn buy-now">Buy Now</button>
                                </form>
                                <a class="btn" href="{{ route('products.edit', ['product' => $slug]) }}" style="background: linear-gradient(135deg, #f97316, #ea580c);">Edit</a>
                                @if (isset($customProducts[$slug]))
                                    <form method="POST" action="{{ route('products.destroy', ['product' => $slug]) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="btn" style="background: #ef4444;" onclick="return confirm('Are you sure?')">Remove</button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('cart.add', ['product' => $slug]) }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn">Add to cart</button>
                                </form>
                                <form method="POST" action="{{ route('cart.buy-now', ['product' => $slug]) }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn buy-now">Buy Now</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                    <p style="font-size: 1.1rem; color: #cbd5e1;">No products found matching your search.</p>
                    <a href="{{ route('products') }}" class="btn" style="margin-top: 16px;">Clear filters</a>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.getElementById('sort-select')?.addEventListener('change', function(e) {
            const searchParam = new URLSearchParams(window.location.search);
            if (e.target.value !== 'none') {
                searchParam.set('sort', e.target.value);
            } else {
                searchParam.delete('sort');
            }
            window.location.search = searchParam.toString();
        });
    </script>
</body>
</html>
