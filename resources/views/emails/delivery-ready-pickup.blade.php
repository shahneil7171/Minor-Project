@extends('emails.layout')

@section('title', 'Order Ready for Pickup')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">Order #{{ $delivery->order->order_number }} is ready for pickup</h1>

    <p style="{{ $text }}">
        The seller has packed order <strong>#{{ $delivery->order->order_number }}</strong>.
        It is now ready for you to pick up and deliver.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Customer:</strong> {{ $delivery->order->shipping_name }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Phone:</strong> {{ $delivery->order->shipping_phone }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Address:</strong>
        {{ $delivery->order->shipping_address }}, {{ $delivery->order->shipping_city }},
        {{ $delivery->order->shipping_state }} {{ $delivery->order->shipping_pincode }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Delivery status:</strong> {{ $delivery->statusLabel() }}</p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('delivery.deliveries.show', ['delivery' => $delivery]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Delivery
        </a>
    </p>
@endsection
