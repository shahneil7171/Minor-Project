@extends('emails.layout')

@section('title', 'Return Request Submitted')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $buyerName = $returnRequest->customer?->name ?? $returnRequest->order?->shipping_name;
    @endphp

    <h1 style="{{ $heading }}">Your return request has been submitted</h1>

    <p style="{{ $text }}">
        Hello {{ $buyerName }}, your return request has been successfully submitted and is
        now waiting for review by our team.
    </p>

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order:</strong> #{{ $returnRequest->order_number }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Reason:</strong> {{ $returnRequest->reason }}</p>
    @if ($returnRequest->description)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Your description:</strong> {{ $returnRequest->description }}</p>
    @endif
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return status:</strong> {{ $returnRequest->statusLabel() }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Estimated refund:</strong> &#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>

    <p style="{{ $text }} margin-top:16px;">
        You can track your return from your KDP MART account.
    </p>

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('returns.show', ['return' => $returnRequest]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Track Your Return
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Regards,<br>KDP MART Team
    </p>
@endsection