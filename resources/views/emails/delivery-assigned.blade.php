@extends('emails.layout')

@section('title', $isReassignment ? 'Delivery Reassigned' : 'New Delivery Assigned')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
    @endphp

    @if ($isReassignment)
        <h1 style="{{ $heading }}">A delivery was reassigned to you</h1>
    @else
        <h1 style="{{ $heading }}">You have a new delivery</h1>
    @endif

    <p style="{{ $text }}">
        Order <strong>#{{ $order->order_number }}</strong> has been assigned to you on
        {{ $delivery->assigned_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}.
    </p>

    <p style="margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Pickup / delivery summary</p>
    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($order->items as $item)
            <li>{{ $item->product_title }} &times; {{ $item->quantity }}@if($item->sku) — SKU {{ $item->sku }}@endif</li>
        @endforeach
    </ul>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Customer:</strong> {{ $order->shipping_name }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Phone:</strong> {{ $order->shipping_phone }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Address:</strong>
        {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}, {{ $order->shipping_country }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Delivery status:</strong> {{ $delivery->statusLabel() }}</p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('delivery.deliveries.show', ['delivery' => $delivery]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Delivery
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Open your delivery dashboard to manage this assignment.
    </p>
@endsection
