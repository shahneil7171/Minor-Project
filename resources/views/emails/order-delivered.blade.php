@extends('emails.layout')

@section('title', 'Order Delivered')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">Your order has been delivered! 🎉</h1>

    <p style="{{ $text }}">
        Hello {{ $order->user?->name ?? $order->shipping_name }}, your order
        <strong>#{{ $order->order_number }}</strong> was delivered on
        {{ $deliveredAt->format('d M Y, h:i A') }}. We hope you love it!
    </p>

    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($order->items as $item)
            <li>{{ $item->product_title }} &times; {{ $item->quantity }}</li>
        @endforeach
    </ul>

    <p style="{{ $text }}">
        <strong>Order total:</strong> &#8377;{{ number_format((float) $order->total, 2) }}<br>
        <strong>Status:</strong> {{ $order->statusLabel() }}
    </p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('orders.show', ['order' => $order]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            View Your Order
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Enjoying your purchase? We would love to hear your feedback — leave a review
        for your products from your order page.
    </p>
@endsection
