<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Complete | KDP MART</title>
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
            max-width: 800px;
            margin: 0 auto;
            border-radius: 24px;
            padding: 24px;
            background: rgba(2,6,23,0.82);
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 24px 50px rgba(0,0,0,0.28);
        }
        .section { margin-bottom: 24px; }
        .section h2 { margin-bottom: 14px; }
        .info { padding: 16px; border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .info p { margin: 8px 0; color: #cbd5e1; }
        .grid { display: grid; gap: 20px; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <h1 style="margin:0 0 10px;">Order completed</h1>
            <p style="margin:0; color:#cbd5e1;">Thank you for your order. Your order details are below.</p>
        </div>

        <div class="section info">
            <h2>Shipping details</h2>
            <p><strong>Name:</strong> {{ $checkout['name'] }}</p>
            <p><strong>Phone:</strong> {{ $checkout['phone'] }}</p>
            <p><strong>Address:</strong> {{ $checkout['address'] }}, {{ $checkout['city'] }}, {{ $checkout['state'] }}, {{ $checkout['pincode'] }}</p>
        </div>

        <div class="section info">
            <h2>Items ordered</h2>
            @php $orderTotal = 0; @endphp
            @foreach ($cart as $item)
                @php
                    $price = floatval(str_replace(['$', ','], '', $item['price']));
                    $subtotal = $price * $item['quantity'];
                    $orderTotal += $subtotal;
                @endphp
                <p><strong>{{ $item['title'] }}</strong>@if(!empty($item['options_text'])) <span style="color:#7dd3fc;">({{ $item['options_text'] }})</span>@endif x{{ $item['quantity'] }} — ${{ number_format($subtotal, 2) }}</p>
            @endforeach
            @if(!empty($orderDiscount = session('order_discount')) && $orderDiscount > 0)
                <p style="color:#34d399;"><strong>Coupon ({{ session('order_coupon') }}):</strong> −${{ number_format((float) $orderDiscount, 2) }}</p>
            @endif
            @if($paymentMethod = session('order_payment'))
                <p><strong>Payment method:</strong> {{ $paymentMethod }}</p>
            @endif
            <p style="margin-top:12px;"><strong>Total amount:</strong> ${{ number_format((float) (session('order_total') ?? $orderTotal), 2) }}</p>
        </div>

        <a class="button" href="{{ route('products') }}">Continue shopping</a>
        @if(!empty($orderId))
            <a class="button" href="{{ route('orders.show', $orderId) }}" style="margin-left: 10px;">Track order</a>
            <a class="button" href="{{ route('orders.index') }}" style="margin-left: 10px;">My Orders</a>
        @endif
    </div>
</body>
</html>
