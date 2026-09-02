@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Delivery Partner — ' . $partner->name)

@section('content')
    <div class="container" style="width:96%; max-width:1300px; margin:30px auto;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
            <div>
                <h1 style="margin:0 0 4px;">{{ $partner->name }}</h1>
                <p style="margin:0; color:#94a3b8;">{{ $partner->email }} · {{ $partner->phone ?? '—' }} · Delivery partner ({{ $partner->status }})</p>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('admin.delivery-partners.edit', $partner) }}" style="padding:9px 16px; border-radius:8px; background:#1e293b; color:#e2e8f0; text-decoration:none; font-weight:700;">Edit</a>
                <form method="POST" action="{{ route('admin.delivery-partners.status', $partner) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $partner->status === 'active' ? 'inactive' : 'active' }}">
                    <button type="submit" style="padding:9px 16px; border:none; border-radius:8px; background:{{ $partner->status === 'active' ? '#b45309' : '#10b981' }}; color:#fff; font-weight:700;">
                        {{ $partner->status === 'active' ? 'Disable' : 'Enable' }}
                    </button>
                </form>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px;">
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Total Assigned</div>
                <div style="font-size:1.25rem; font-weight:800; color:#60a5fa;">{{ $stats['total'] }}</div>
            </div>
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Active</div>
                <div style="font-size:1.25rem; font-weight:800; color:#fbbf24;">{{ $stats['active'] }}</div>
            </div>
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Delivered</div>
                <div style="font-size:1.25rem; font-weight:800; color:#34d399;">{{ $stats['delivered'] }}</div>
            </div>
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Failed</div>
                <div style="font-size:1.25rem; font-weight:800; color:#f87171;">{{ $stats['failed'] }}</div>
            </div>
        </div>

        <h2 style="font-size:1.1rem; margin-bottom:12px;">Assigned Deliveries & History</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>City</th><th>Order Status</th><th>Delivery Status</th><th>Assigned</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $delivery)
                        <tr>
                            <td><strong>#{{ $delivery->order->order_number }}</strong></td>
                            <td>{{ $delivery->order->shipping_name }}</td>
                            <td>{{ $delivery->order->shipping_city }}</td>
                            <td>{{ $delivery->order->statusLabel() }}</td>
                            <td><span class="badge {{ $delivery->status }}">{{ $delivery->status }}</span></td>
                            <td style="font-size:.8rem; color:#94a3b8;">{{ $delivery->assigned_at?->format('M d, Y h:i A') }}</td>
                            <td class="actions"><a href="{{ route('admin.deliveries.show', $delivery) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No deliveries assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
