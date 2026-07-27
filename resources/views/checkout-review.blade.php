<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Order | KDP MART</title>
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
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; }
        .header a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .card { border-radius: 20px; padding: 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .card h2 { margin-top: 0; color: #fff; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.12); }
        .summary-row:last-child { border-bottom: none; }
        .summary-total { padding-top: 16px; margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.12); display: flex; justify-content: space-between; align-items: center; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0 0 6px;">Review Order</h1>
                <p style="margin:0; color:#cbd5e1;">Review the total before continuing to enter your personal details.</p>
            </div>
            <a href="{{ route('cart.index') }}">Back to cart</a>
        </div>

        <div class="card">
            @if (empty($cart))
                <p style="margin:0; color:#cbd5e1;">No items are selected yet.</p>
            @else
                @foreach ($cart as $item)
                    @php
                        $price = floatval(str_replace(['$', ','], '', $item['price']));
                        $subtotal = $price * $item['quantity'];
                    @endphp
                    <div class="summary-row">
                        <div>
                            <strong>{{ $item['title'] }}</strong>
                            <div style="color:#94a3b8; font-size:0.95rem;">Qty: {{ $item['quantity'] }}</div>
                        </div>
                        <div>${{ number_format($subtotal, 2) }}</div>
                    </div>
                @endforeach
                <div class="summary-total">
                    <strong>Total</strong>
                    <strong>${{ number_format($total, 2) }}</strong>
                </div>
            @endif
        </div>

        <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px; flex-wrap:wrap;">
            <a class="button" href="{{ route('checkout.index') }}">Continue</a>
        </div>
    </div>
</body>
</html>
