@extends('emails.layout')

@section('title', $isReassignment ? 'Delivery Reassigned' : 'New Delivery Assigned')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $textSmall = 'margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#4b5563;';
        $label = 'margin:18px 0 6px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;';

        // Values are supplied by DeliveryAssignedMail; the fallbacks keep the
        // template usable if it is ever rendered from another code path.
        $partnerName = $partnerName ?? $delivery->deliveryPartner?->name;
        $itemsCount = $itemsCount ?? (int) $order->items->sum('quantity');
        $sellerNames = $sellerNames ?? $order->items
            ->map(fn ($item) => $item->seller?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    @endphp

    @if (filled($partnerName))
        <p style="{{ $text }}">Hello {{ $partnerName }},</p>
    @endif

    @if ($isReassignment)
        <h1 style="{{ $heading }}">A delivery was reassigned to you</h1>
    @else
        <h1 style="{{ $heading }}">You have been assigned a new delivery</h1>
    @endif

    <p style="{{ $text }}">
        Order <strong>#{{ $order->order_number }}</strong> has been assigned to you on
        {{ $delivery->assigned_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}.
    </p>

    <p style="{{ $label }}">Order details</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order Number:</strong> #{{ $order->order_number }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Customer:</strong> {{ $order->shipping_name }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Items:</strong> {{ $itemsCount }}</p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Order Total:</strong> &#8377;{{ number_format((float) $order->total, 2) }}</p>

    <p style="{{ $label }}">Delivery address</p>
    <p style="{{ $textSmall }}">
        {{ $order->shipping_address }}, {{ $order->shipping_city }},
        {{ $order->shipping_state }} {{ $order->shipping_pincode }}, {{ $order->shipping_country }}
    </p>
    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Customer phone:</strong> {{ $order->shipping_phone }}</p>

    <p style="{{ $label }}">Pickup / delivery summary</p>
    <ul style="margin:0 0 16px 0; padding:0 0 0 20px; font-size:14px; line-height:1.9; color:#374151;">
        @foreach ($order->items as $item)
            <li>{{ $item->product_title }} &times; {{ $item->quantity }}@if($item->sku) — SKU {{ $item->sku }}@endif</li>
        @endforeach
    </ul>

    @if (! empty($sellerNames))
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Seller:</strong> {{ implode(', ', $sellerNames) }}</p>
    @endif

    <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Delivery Status:</strong> {{ $delivery->statusLabel() }}</p>

    @if ($order->notes)
        <p style="{{ $textSmall }}"><strong style="color:#2d2f36;">Delivery instructions:</strong> {{ $order->notes }}</p>
    @endif

    <p style="margin:22px 0 0 0;">
        <a href="{{ route('delivery.deliveries.show', ['delivery' => $delivery]) }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Open Delivery
        </a>
    </p>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Please open your KDP MART delivery dashboard to view the complete delivery information and process the order.
    </p>

    <p style="margin:14px 0 0 0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        Regards,<br>KDP MART Team
    </p>
@endsection
