@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Delivery Details')

@section('content')
    <div class="container" style="width:96%; max-width:1200px; margin:30px auto;">
        <h1 style="margin-bottom:6px;">Delivery — Order #{{ $order->order_number }}</h1>
        <p style="color:#94a3b8; margin-bottom:24px;">
            Order status: <strong>{{ $order->statusLabel() }}</strong> ·
            Delivery status: <strong>{{ $delivery->statusLabel() }}</strong> ·
            Partner: <strong>{{ $delivery->deliveryPartner?->name ?? '—' }}</strong>
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:14px; margin-bottom:24px;">
            <div style="border:1px solid #26304a; border-radius:12px; padding:18px; background:#111827;">
                <h3 style="margin:0 0 12px; font-size:1rem;">Customer</h3>
                <p style="margin:0 0 6px;">{{ $order->shipping_name }}</p>
                <p style="margin:0 0 6px; color:#94a3b8;">{{ $order->shipping_phone }}</p>
                <p style="margin:0; color:#94a3b8;">
                    {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}, {{ $order->shipping_country }}
                </p>
            </div>

            <div style="border:1px solid #26304a; border-radius:12px; padding:18px; background:#111827;">
                <h3 style="margin:0 0 12px; font-size:1rem;">Delivery Timeline</h3>
                <p style="margin:0 0 6px; color:#94a3b8;">Assigned: {{ $delivery->assigned_at?->format('M d, Y h:i A') ?? '—' }}</p>
                <p style="margin:0 0 6px; color:#94a3b8;">Picked up: {{ $delivery->picked_up_at?->format('M d, Y h:i A') ?? '—' }}</p>
                <p style="margin:0 0 6px; color:#94a3b8;">Out for delivery: {{ $delivery->out_for_delivery_at?->format('M d, Y h:i A') ?? '—' }}</p>
                <p style="margin:0 0 6px; color:#94a3b8;">Delivered: {{ $delivery->delivered_at?->format('M d, Y h:i A') ?? '—' }}</p>
                <p style="margin:0; color:#94a3b8;">Failed: {{ $delivery->failed_at?->format('M d, Y h:i A') ?? '—' }}</p>
                @if ($delivery->delivery_notes)
                    <p style="margin:10px 0 0; font-size:.85rem;"><strong>Notes:</strong> {{ $delivery->delivery_notes }}</p>
                @endif
            </div>

            <div style="border:1px solid #26304a; border-radius:12px; padding:18px; background:#111827;">
                <h3 style="margin:0 0 12px; font-size:1rem;">Management</h3>
                <form method="POST" action="{{ route('admin.deliveries.reassign', $delivery) }}" style="margin-bottom:14px;">
                    @csrf
                    <label style="display:block; font-size:.8rem; color:#94a3b8; margin-bottom:6px;">Reassign to active partner</label>
                    <div style="display:flex; gap:8px;">
                        <select name="delivery_partner_id" required style="flex:1; padding:8px 10px; border-radius:8px; border:1px solid #374151; background:#111827; color:#e5e7eb;">
                            <option value="">Choose partner…</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}" {{ $delivery->delivery_partner_id === $partner->id ? 'selected' : '' }}>{{ $partner->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" style="padding:8px 14px; border:none; border-radius:8px; background:#2563eb; color:#fff; font-weight:700;">Reassign</button>
                    </div>
                </form>
                @if ($delivery->isActive())
                    <form method="POST" action="{{ route('admin.deliveries.status', $delivery) }}">
                        @csrf
                        <input type="hidden" name="status" value="failed">
                        <button type="submit" style="padding:8px 14px; border:none; border-radius:8px; background:#b91c1c; color:#fff; font-weight:700;">Mark as Failed</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Product</th><th>SKU</th><th>Variant</th><th>Qty</th><th>Price</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_title }}</td>
                            <td>{{ $item->sku ?? '—' }}</td>
                            <td>{{ $item->options_text ?? '—' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ '$' . number_format((float) $item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
