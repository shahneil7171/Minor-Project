<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | KDP MART</title>
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
        .checkout-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 24px; }
        .card { border-radius: 20px; padding: 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .card h2 { margin-top: 0; color: #fff; }
        .field { margin-bottom: 16px; }
        .field label { display: block; margin-bottom: 8px; color: #cbd5e1; }
        .field input, .field textarea { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.16); background: rgba(255,255,255,0.06); color: #f8fafc; }
        .field textarea { min-height: 120px; resize: vertical; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; cursor: pointer; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.12); }
        .summary-row:last-child { border-bottom: none; }
        .summary-total { padding-top: 16px; margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.12); display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0 0 6px;">Checkout</h1>
                <p style="margin:0; color:#cbd5e1;">Confirm your order and enter your personal details.</p>
            </div>
            <a href="{{ route('cart.index') }}">Back to cart</a>
        </div>

        <div class="checkout-grid">
            <div class="card">
                <h2>Shipping details</h2>
                <form method="POST" action="{{ route('checkout.submit') }}">
                    @csrf
                    <div class="field">
                        <label for="name">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $shippingAddress?->full_name) }}" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone number</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $shippingAddress?->phone) }}" required>
                    </div>
                    <div class="field">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required>{{ old('address', $shippingAddress ? trim($shippingAddress->house_number . ', ' . $shippingAddress->street_address, ', ') : null) }}</textarea>
                    </div>
                    <div class="field">
                        <label for="city">City</label>
                        <input id="city" name="city" type="text" value="{{ old('city', $shippingAddress?->city) }}" required>
                    </div>
                    <div class="field">
                        <label for="state">State</label>
                        <input id="state" name="state" type="text" value="{{ old('state', $shippingAddress?->state) }}" required>
                    </div>
                    <div class="field">
                        <label for="pincode">Pincode</label>
                        <input id="pincode" name="pincode" type="text" value="{{ old('pincode', $shippingAddress?->pincode) }}" required>
                    </div>
                    <button class="button" type="submit">Confirm and continue</button>
                </form>
            </div>
            <div class="card">
                <h2>Order summary</h2>
                @if (empty($cart))
                    <p style="color:#cbd5e1;">Your cart is empty.</p>
                @else
                    @foreach ($cart as $item)
                        @php
                            $price = floatval(str_replace(['$', ','], '', $item['price']));
                            $subtotal = $price * $item['quantity'];
                        @endphp
                        <div class="summary-row">
                            <div>
                                <strong>{{ $item['title'] }}</strong>
                                @if(!empty($item['options_text']))
                                    <div style="color:#7dd3fc; font-size:0.88rem;">{{ $item['options_text'] }}</div>
                                @endif
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
        </div>
    </div>
</body>
</html>
