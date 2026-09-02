@extends('emails.layout')

@section('title', 'Order Confirmation')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $th = 'padding:10px 8px; text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; border-bottom:1px solid #e5e7eb;';
        $td = 'padding:10px 8px; font-size:14px; color:#374151; border-bottom:1px solid #f1f2f6; vertical-align:top;';
        $labelCell = 'padding:4px 0; font-size:14px; color:#6b7280;';
        $valueCell = 'padding:4px 0 4px 24px; font-size:14px; color:#374151; text-align:right;';
    @endphp

    <h1 style="{{ $heading }}">Thank you for your order, {{ $order->user?->name ?? $order->shipping_name }}!</h1>

    <p style="{{ $text }}">
        We have received your order and it is now being processed. Here are your order details:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px 0; background-color:#f7f8fc; border-radius:10px;">
        <tr>
            <td style="padding:14px 16px; font-size:14px; color:#374151; line-height:1.9;">
                <strong style="color:#2d2f36;">Order number:</strong> #{{ $order->order_number }}<br>
                <strong style="color:#2d2f36;">Placed on:</strong> {{ $order->created_at?->format('d M Y, h:i A') }}<br>
                <strong style="color:#2d2f36;">Status:</strong> {{ $order->statusLabel() }}<br>
                <strong style="color:#2d2f36;">Payment method:</strong> {{ $order->payment_method }}
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 6px 0; border:1px solid #eceef5;">
        <tr>
            <th style="{{ $th }}">Product</th>
            <th style="{{ $th }} text-align:center;">Qty</th>
            <th style="{{ $th }} text-align:right;">Price</th>
            <th style="{{ $th }} text-align:right;">Total</th>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td style="{{ $td }}">
                    {{ $item->product_title }}
                    @if ($item->options_text)
                        <br><span style="font-size:12px; color:#8a8f9e;">{{ $item->options_text }}</span>
                    @endif
                </td>
                <td style="{{ $td }} text-align:center;">{{ $item->quantity }}</td>
                <td style="{{ $td }} text-align:right;">&#8377;{{ number_format((float) $item->price, 2) }}</td>
                <td style="{{ $td }} text-align:right;">&#8377;{{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="right" style="margin:10px 0 18px 0; min-width:260px;">
        <tr>
            <td style="{{ $labelCell }}">Subtotal</td>
            <td style="{{ $valueCell }}">&#8377;{{ number_format((float) $order->subtotal, 2) }}</td>
        </tr>
        @if ((float) $order->discount_amount > 0)
            <tr>
                <td style="{{ $labelCell }} color:#11998e;">Discount @if ($order->coupon_code)({{ $order->coupon_code }})@endif</td>
                <td style="{{ $valueCell }} color:#11998e;">&#8722;&#8377;{{ number_format((float) $order->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->tax > 0)
            <tr>
                <td style="{{ $labelCell }}">Tax</td>
                <td style="{{ $valueCell }}">&#8377;{{ number_format((float) $order->tax, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->shipping_cost > 0)
            <tr>
                <td style="{{ $labelCell }}">Shipping</td>
                <td style="{{ $valueCell }}">&#8377;{{ number_format((float) $order->shipping_cost, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td style="border-top:1px solid #e5e7eb; padding:10px 0 0 0; font-size:16px; font-weight:700; color:#2d2f36;">Total</td>
            <td style="border-top:1px solid #e5e7eb; padding:10px 0 0 24px; font-size:16px; font-weight:700; color:#2d2f36; text-align:right;">&#8377;{{ number_format((float) $order->total, 2) }}</td>
        </tr>
    </table>
    <div style="clear:both;"></div>

    <p style="{{ $text }} margin-bottom:6px;">
        <strong>Shipping address:</strong><br>
        {{ $order->shipping_name }}<br>
        {{ $order->shipping_phone }}<br>
        {{ $order->shipping_address }}<br>
        {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}<br>
        {{ $order->shipping_country }}
    </p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('orders.index') }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            View My Orders
        </a>
    </p>
@endsection
