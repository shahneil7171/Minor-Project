@extends('emails.layout')

@section('title', 'New Return Pickup Assigned')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $label = 'margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;';
        $order = $returnRequest->order;
        $partnerName = $returnRequest->deliveryPartner?->name;
    @endphp

    @if ($partnerName)
        <p style="{{ $text }}">Hello {{ $partnerName }},</p>
    @endif

    <h1 style="{{ $heading }}">A return pickup has been assigned to you</h1>

    <p style="{{ $text }}">
        Return <strong>{{ $returnRequest->return_number }}</strong> for order
        <strong>#{{ $returnRequest->order_number }}</strong> must be collected from the customer and
        returned to the seller/warehouse.
    </p>

    <p style="{{ $label }}">Pickup details</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return request:</strong> {{ $returnRequest->return_number }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order:</strong> #{{ $returnRequest->order_number }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    @if ($returnRequest->seller)
        {{-- Seller/store name only — never seller payment details. --}}
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return to (seller):</strong> {{ $returnRequest->seller->name }}</p>
    @endif

    <p style="{{ $label }}">Customer</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Name:</strong> {{ $order?->shipping_name }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Phone:</strong> {{ $order?->shipping_phone }}</p>
    <p style="{{ $textSmall }}">
        <strong style="color:#2d2f36;">Pickup address:</strong>
        {{ $order?->shipping_address }}, {{ $order?->shipping_city }},
        {{ $order?->shipping_state }} {{ $order?->shipping_pincode }}, {{ $order?->shipping_country }}
    </p>

    <p style="{{ $label }}">Instructions</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return status:</strong> {{ $returnRequest->statusLabel() }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Reason:</strong> {{ $returnRequest->reason }}</p>
    @if ($order?->notes)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Customer instructions:</strong> {{ $order->notes }}</p>
    @endif
    @if ($returnRequest->pickup_notes)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Pickup notes:</strong> {{ $returnRequest->pickup_notes }}</p>
    @endif

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('delivery.returns.show', ['return' => $returnRequest]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Return Pickup
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Please open your KDP MART delivery dashboard to view the complete pickup information.
    </p>

    <p style="margin:14px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Regards,<br>KDP MART Team
    </p>
@endsection