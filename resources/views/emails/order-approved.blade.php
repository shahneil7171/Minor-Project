@extends('emails.layout')

@section('title', 'Order Approved')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">Your order has been approved!</h1>

    <p style="{{ $text }}">
        Hello {{ $order->user?->name ?? $order->shipping_name }}, good news — your order
        <strong>#{{ $order->order_number }}</strong> was approved on
        {{ $order->approved_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }} and is now being prepared.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Current status:</strong> {{ $order->statusLabel() }}</p>

    <p style="margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Items in this order</p>
    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($order->items as $item)
            <li>{{ $item->product_title }} &times; {{ $item->quantity }}</li>
        @endforeach
    </ul>

    <p style="{{ $text }} margin-bottom:6px;">
        <strong>Delivery address:</strong><br>
        {{ $order->shipping_name }}<br>
        {{ $order->shipping_address }}<br>
        {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}<br>
        {{ $order->shipping_country }}
    </p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('orders.show', ['order' => $order]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Track Your Order
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        We will notify you as soon as your order ships.
    </p>
@endsection
