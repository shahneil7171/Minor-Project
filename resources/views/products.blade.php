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
        .card-link { display: block; color: inherit; text-decoration: none; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .header { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0 0 6px;">Featured products</h1>
                <p style="margin:0; color:#cbd5e1;">A polished storefront experience for authenticated shoppers.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn">Back to dashboard</a>
        </div>
        <div class="grid">
            @foreach ($products as $slug => $product)
                <a class="card-link" href="{{ route('product.show', ['product' => $slug]) }}">
                    <div class="card">
                        <h3>{{ $product['title'] }}</h3>
                        <p>{{ $product['subtitle'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</body>
</html>
