@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Deliveries')

@section('content')
    <div class="container" style="width:96%; max-width:1400px; margin:30px auto;">
        <h1 style="margin-bottom:20px;">Deliveries</h1>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:22px;">
            @foreach ([
                'Pending Approval' => [$stats['pending_approval'], '#f472b6', route('admin.orders.index', ['status' => 'pending'])],
                'Unassigned'       => [$stats['unassigned'], '#fbbf24', '#unassigned'],
                'Assigned'         => [$stats['assigned'], '#60a5fa', '#'],
                'Out for Delivery' => [$stats['out_for_delivery'], '#a78bfa', '#'],
                'Delivered'        => [$stats['delivered'], '#34d399', '#'],
                'Failed'           => [$stats['failed'], '#f87171', '#'],
            ] as $label => [$value, $color, $url])
                <a href="{{ $url }}" style="text-decoration:none;">
                    <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                        <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">{{ $label }}</div>
                        <div style="font-size:1.25rem; font-weight:800; color:{{ $color }};">{{ $value }}</div>
                    </div>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.deliveries.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search order #, customer, city or partner" style="padding:9px 12px; border-radius:8px; border:1px solid #26304a; background:#111827; color:#e5e7eb; min-width:260px;">
            <select name="partner" style="padding:9px 12px; border-radius:8px; border:1px solid #26304a; background:#111827; color:#e5e7eb;">
                <option value="">All partners</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}" {{ $partnerId == $partner->id ? 'selected' : '' }}>{{ $partner->name }}{{ $partner->status !== 'active' ? ' (inactive)' : '' }}</option>
                @endforeach
            </select>
            <select name="status" style="padding:9px 12px; border-radius:8px; border:1px solid #26304a; background:#111827; color:#e5e7eb;">
                <option value="all">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ $statusLabels[$s] }}</option>
                @endforeach
            </select>
            <button type="submit" style="padding:9px 18px; border:none; border-radius:8px; background:#2563eb; color:#fff; font-weight:700;">Filter</button>
            <a href="{{ route('admin.deliveries.index') }}" style="padding:9px 14px; border-radius:8px; background:#1e293b; color:#e2e8f0; text-decoration:none;">Reset</a>
        </form>

        <div class="table-wrap" style="margin-bottom:30px;">
            <table>
                <thead>
                    <tr>
                        <th>Order</th><th>Customer</th><th>City</th><th>Items</th>
                        <th>Delivery Partner</th><th>Delivery Status</th><th>Assigned</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td><strong>#{{ $delivery->order->order_number }}</strong><div style="color:#94a3b8; font-size:.8rem;">{{ $delivery->order->statusLabel() }}</div></td>
                            <td>{{ $delivery->order->shipping_name }}<div class="user">{{ $delivery->order->shipping_phone }}</div></td>
                            <td>{{ $delivery->order->shipping_city }}</td>
                            <td>{{ $delivery->order->items->sum('quantity') }}</td>
                            <td>{{ $delivery->deliveryPartner?->name ?? '—' }}</td>
                            <td><span class="badge {{ $delivery->status }}">{{ $delivery->status }}</span></td>
                            <td style="font-size:.8rem; color:#94a3b8;">{{ $delivery->assigned_at?->format('M d, Y h:i A') }}</td>
                            <td class="actions"><a href="{{ route('admin.deliveries.show', $delivery) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">No deliveries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 id="unassigned" style="font-size:1.15rem; margin-bottom:14px;">Approved orders without a delivery partner</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Assign Delivery Partner</th></tr>
                </thead>
                <tbody>
                    @forelse ($unassigned as $order)
                        <tr>
                            <td><strong>#{{ $order->order_number }}</strong></td>
                            <td>{{ $order->shipping_name }}<div class="user">{{ $order->shipping_phone }}</div></td>
                            <td>{{ $order->items->sum('quantity') }}</td>
                            <td>{{ '$' . number_format((float) $order->total, 2) }}</td>
                            <td><span class="badge {{ $order->status }}">{{ $order->status }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.orders.assign-delivery', $order) }}" style="display:flex; gap:6px; align-items:center;">
                                    @csrf
                                    <select name="delivery_partner_id" required style="padding:7px 10px; border-radius:8px; border:1px solid #374151; background:#111827; color:#e5e7eb;">
                                        <option value="">Choose partner…</option>
                                        @foreach ($partners->where('status', 'active') as $partner)
                                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit">Assign</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No unassigned approved orders. Every approved order has a delivery partner.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
