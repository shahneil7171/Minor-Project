@extends('emails.layout')

@section('title', 'Return Request Rejected')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $buyerName = $returnRequest->customer?->name ?? $returnRequest->order?->shipping_name;
    @endphp

    <h1 style="{{ $heading }}">Your return request was rejected</h1>

    <p style="{{ $text }}">
        Hello {{ $buyerName }}, unfortunately your return request for order
        <strong>#{{ $returnRequest->order_number }}</strong> was rejected.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return status:</strong> {{ $returnRequest->statusLabel() }}</p>

    @if ($returnRequest->rejection_reason)
        <p style="margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Reason for rejection</p>
        <p style="{{ $textSmall }}">{{ $returnRequest->rejection_reason }}</p>
    @endif

    <p style="{{ $text }} margin-top:16px;">
        If you believe this decision is incorrect, please contact our support team from your
        KDP MART account and quote return request {{ $returnRequest->return_number }}.
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