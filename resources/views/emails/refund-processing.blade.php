@extends('emails.layout')

@section('title', 'Refund Processing')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $buyerName = $returnRequest->customer?->name ?? $returnRequest->order?->shipping_name;
    @endphp

    <h1 style="{{ $heading }}">Your refund is being processed</h1>

    <p style="{{ $text }}">
        Hello {{ $buyerName }}, the refund for your return
        <strong>{{ $returnRequest->return_number }}</strong> (order
        <strong>#{{ $returnRequest->order_number }}</strong>) is now being processed.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refund amount:</strong> &#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>
    @if ($returnRequest->refund_reference)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Reference:</strong> {{ $returnRequest->refund_reference }}</p>
    @endif
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refund status:</strong> {{ $returnRequest->refundStatusLabel() }}</p>

    <p style="{{ $text }} margin-top:16px;">
        This email confirms the store has started processing your refund.
        No automatic bank/UPI transfer is claimed — actual settlement depends on
        the store's configured payment/refund process.
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
