<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | KDP MART</title>
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
        .cart-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .cart-table th, .cart-table td { padding: 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.12); text-align: left; }
        .cart-table th { color: #94a3b8; font-weight: 700; }
        .cart-total { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-radius: 18px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .cart-total span { font-size: 1rem; color: #cbd5e1; }
        .cart-total strong { font-size: 1.4rem; }
        .empty-state { padding: 48px; border-radius: 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); text-align: center; color: #cbd5e1; }
        .empty-state a { color: #93c5fd; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0 0 6px;">Your Shopping Cart</h1>
                <p style="margin:0; color:#cbd5e1;">Review your selections before checkout.</p>
            </div>
            <a href="{{ route('products') }}">Continue shopping</a>
        </div>

        @if (empty($cart))
            <div class="empty-state">
                <h2 style="margin:0 0 8px; color:#f8fafc;">Your cart is empty.</h2>
                <p style="margin:0 0 16px;">Add a product from the product page to see it here.</p>
                <a href="{{ route('products') }}">Browse products</a>
            </div>
        @else
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @php $total = 0; @endphp
                    @foreach ($cart as $item)
                        @php
                            $price = floatval(str_replace(['$', ','], '', $item['price']));
                            $subtotal = $price * $item['quantity'];
                            $total += $subtotal;
                        @endphp
                        <tr>
                            <td>{{ $item['title'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>{{ $item['price'] }}</td>
                            <td>${{ number_format($subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cart-total">
                <span>Total</span>
                <strong>${{ number_format($total, 2) }}</strong>
            </div>
            <div style="margin-top: 24px; display:flex; justify-content:flex-end;">
                <a href="{{ route('checkout.index') }}" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #10b981, #059669); color:white; text-decoration:none; font-weight:700;">Continue to checkout</a>
            </div>
        @endif
    </div>
</body>
</html>
