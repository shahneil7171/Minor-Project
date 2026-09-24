@extends('emails.layout')

@section('title', 'New Approved Order')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">A new order needs your preparation</h1>

    <p style="{{ $text }}">
        Hello, order <strong>#{{ $order->order_number }}</strong> was approved on
        {{ $order->approved_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }} and contains products
        from your store. Please prepare the items below for delivery.
    </p>

    <p style="margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Your items in this order</p>
    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($items as $item)
            <li>
                {{ $item->product_title }} &times; {{ $item->quantity }}
                @if ($item->sku) — SKU {{ $item->sku }}@endif
                @if ($item->options_text) ({{ $item->options_text }})@endif
                — &#8377;{{ number_format((float) $item->price, 2) }}
            </li>
        @endforeach
    </ul>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order date:</strong> {{ $order->created_at->format('d M Y, h:i A') }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Buyer:</strong> {{ $order->shipping_name }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Buyer phone:</strong> {{ $order->shipping_phone }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Delivery address:</strong>
        {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}, {{ $order->shipping_country }}</p>
    @if ($order->shipping_method)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Shipping method:</strong> {{ $order->shipping_method }}</p>
    @endif
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order status:</strong> {{ $order->statusLabel() }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Next Action:</strong> Please process and pack the order. Mark it as “Ready for Pickup” when the package is ready for collection.</p>
    @if ($order->notes)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Buyer instructions:</strong> {{ $order->notes }}</p>
    @endif

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('seller.orders.show', ['order' => $order]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Seller Order
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        This email contains only the products from your store in this order.
    </p>
@endsection
