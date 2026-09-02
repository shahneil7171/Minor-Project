@extends('emails.layout')

@section('title', 'Order Picked Up')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">Your products are on the way</h1>

    <p style="{{ $text }}">
        The delivery partner has collected order <strong>#{{ $order->order_number }}</strong>
        on {{ now()->format('d M Y, h:i A') }}. Your items below are now with the courier.
    </p>

    <p style="margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Your items picked up</p>
    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($items as $item)
            <li>{{ $item->product_title }} &times; {{ $item->quantity }}</li>
        @endforeach
    </ul>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order status:</strong> {{ $order->statusLabel() }}</p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('seller.orders.show', ['order' => $order]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Seller Order
        </a>
    </p>
@endsection
