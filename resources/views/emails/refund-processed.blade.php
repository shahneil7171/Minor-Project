@extends('emails.layout')

@section('title', 'Refund Completed')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $label = 'margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;';
        $buyerName = $returnRequest->customer?->name ?? $returnRequest->order?->shipping_name;
    @endphp

    <h1 style="{{ $heading }}">Your refund has been completed</h1>

    <p style="{{ $text }}">
        Hello {{ $buyerName }}, your refund for order
        <strong>#{{ $returnRequest->order_number }}</strong> has been completed.
    </p>

    <p style="{{ $label }}">Refund breakdown</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product amount:</strong> &#8377;{{ number_format((float) $returnRequest->refund_amount - (float) $returnRequest->shipping_refund_amount, 2) }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Shipping refund:</strong> &#8377;{{ number_format((float) $returnRequest->shipping_refund_amount, 2) }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Total refund:</strong> &#8377;{{ number_format((float) $returnRequest->refund_amount, 2) }}</p>

    <p style="{{ $label }}">Return details</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Return request:</strong> {{ $returnRequest->return_number }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Product:</strong> {{ $returnRequest->product_title }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Quantity:</strong> {{ $returnRequest->quantity }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refund status:</strong> {{ $returnRequest->refundStatusLabel() }}</p>
    @if ($returnRequest->refunded_at)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Refunded on:</strong> {{ $returnRequest->refunded_at->format('d M Y, h:i A') }}</p>
    @endif
    @if ($returnRequest->refund_reference)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Reference:</strong> {{ $returnRequest->refund_reference }}</p>
    @endif

    <p style="{{ $text }} margin-top:16px;">
        This email confirms that the store has recorded your refund as completed for this return.
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