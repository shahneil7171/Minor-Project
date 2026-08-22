<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #050a1a; color: #f8fafc; }
        .page { max-width: 900px; margin: 0 auto; padding: 48px 20px 80px; }
        .back { display: inline-flex; align-items: center; gap: 8px; color: #93c5fd; text-decoration: none; font-weight: 700; margin-bottom: 26px; }
        .back:hover { text-decoration: underline; }
        h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.6rem); letter-spacing: -0.02em; }
        h1 em { font-style: normal; background: linear-gradient(90deg, #60a5fa, #f43f5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        h2 { font-size: 1.15rem; margin: 26px 0 10px; color: #bfdbfe; }
        p { color: #cbd5e1; line-height: 1.7; }
        .card { border-radius: 18px; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); padding: 28px; margin-bottom: 18px; }
        .values { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .value { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 18px; }
        .value h3 { margin: 0 0 6px; font-size: 1rem; }
        .value p { margin: 0; font-size: .9rem; color: #94a3b8; line-height: 1.55; }
        .cta { display: inline-flex; align-items: center; padding: 9px 20px; border-radius: 999px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; text-decoration: none; font-weight: 700; font-size: .875rem; }
    </style>
</head>
<body>
    <div class="page">
        <a class="back" href="{{ route('home') }}">← Back to store</a>
        <h1>About <em>KDP MART</em></h1>

        <div class="card">
            <p><strong>KDP MART</strong> is an open-source e-commerce storefront built to show how a complete online shop fits together — browsing, search, categories, reviews, wishlists, coupons, checkout and order tracking — with clean, understandable Laravel code.</p>
            <p>From the latest tech and accessories to everyday essentials, we curate products that offer genuine value, and every order is tracked from checkout all the way to your door.</p>
        </div>

        <div class="card">
            <h2>What we stand for</h2>
            <div class="values">
                <div class="value">
                    <h3>🛍️ Curated catalog</h3>
                    <p>A focused range of products organised into clear categories so you always find what you need.</p>
                </div>
                <div class="value">
                    <h3>💰 Honest pricing</h3>
                    <p>Special offers come straight from the catalog — real discounts on real products.</p>
                </div>
                <div class="value">
                    <h3>🚚 Reliable delivery</h3>
                    <p>Free delivery on qualifying orders, with tracking updates at every step of the journey.</p>
                </div>
                <div class="value">
                    <h3>🔒 Secure by design</h3>
                    <p>Validated forms, protected checkouts and role-based access keep your data safe.</p>
                </div>
            </div>
        </div>

        <div class="card" style="text-align:center;">
            <h2 style="margin-top:0;">Ready to shop?</h2>
            <p>Browse the full catalog or reach out — we're happy to help.</p>
            <a class="cta" href="{{ route('products') }}">Browse products →</a>
            &nbsp;
            <a class="cta" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2);" href="{{ route('contact') }}">Contact us</a>
        </div>
    </div>
</body>
</html>
