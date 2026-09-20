@extends('emails.layout')

@section('title', 'Return Request Approved')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $buyerName = $returnRequest->customer?->name ?? $returnRequest->order?->shipping_name;
    @endphp

    <h1 style="{{ $heading }}">Your return request has been approved</h1>

    <p style="{{ $text }}">
        Hello {{ $buyerName }}, your return request for order
        <strong>#{{ $returnRequest->order_number }}</strong> has been approved.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return status:</strong> {{ $returnRequest->statusLabel() }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Approved on:</strong> {{ $returnRequest->approved_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refund status:</strong> {{ $returnRequest->refundStatusLabel() }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refund amount:</strong> &#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>
    @if ((float) $returnRequest->shipping_refund_amount > 0)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Includes shipping refund:</strong> &#8377;{{ number_format((float) $returnRequest->shipping_refund_amount, 2) }}</p>
    @endif

    <p style="{{ $text }} margin-top:16px;">
        Our delivery partner will collect the product from your delivery address. Please keep the
        product in acceptable condition with its original packaging and accessories where applicable.
    </p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('returns.show', ['return' => $returnRequest]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            View Return Details
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Regards,<br>KDP MART Team
    </p>
@endsection