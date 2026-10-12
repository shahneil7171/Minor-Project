@extends('admin.layouts.panel')

@section('title', 'Store Fulfilment')

@php
    /**
     * ADMIN STORE FULFILMENT — orders that contain the Admin store's OWN
     * products (the seed catalog plus anything an admin added directly).
     *
     * Those products have no seller account, so this is where the store
     * prepares and dispatches them. The "Preparing by ..." text is derived from
     * App\Support\FulfilmentOwner via $order->fulfilmentPreparingLabel(), so it
     * always names the REAL owner (never a hardcoded "Seller").
     */
    $storeLabel = \App\Support\FulfilmentOwner::storeOwnerLabel();
@endphp

@section('content')
<div class="page-head">
    <div>
        <h2>Store Fulfilment</h2>
        <p>
            Orders containing {{ $storeLabel }} products.
            These are fulfilled by the store itself — there is no separate seller.
        </p>
    </div>
    <div class="row-actions">
        <a href="{{ route('admin.orders.index') }}">All orders</a>
    </div>
</div>

@if (session('success'))
    <div class="ka-flash">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="ka-flash error">{{ session('error') }}</div>
@endif

<style>
    .so-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px; margin-bottom:20px; }
    .so-stat { background:var(--ka-panel); border:1px solid var(--ka-border); border-radius:12px; padding:16px 18px; }
    .so-stat span { display:block; color:var(--ka-muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
    .so-stat strong { display:block; margin-top:6px; font-size:1.6rem; color:#fff; }
    .so-card { background:var(--ka-panel); border:1px solid var(--ka-border); border-radius:12px; padding:8px; overflow-x:auto; }
    .so-table { width:100%; border-collapse:collapse; }
    .so-table th, .so-table td { padding:12px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,.06); font-size:.88rem; }
    .so-table th { color:var(--ka-muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; }
    .so-owner { color:#cbd5e1; font-size:.82rem; }
    .so-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border:none; border-radius:9px; color:#fff; font-weight:700; font-size:.84rem; cursor:pointer; text-decoration:none; }
    .so-btn.primary { background:#2563eb; }
    .so-btn.cyan { background:#0891b2; }
    .so-btn.indigo { background:#4f46e5; }
    .so-btn:disabled { opacity:.5; cursor:not-allowed; }
    .so-pager { margin-top:20px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
    .so-pager a, .so-pager span { display:inline-flex; align-items:center; justify-content:center; padding:8px 13px; border-radius:8px; text-decoration:none; font-weight:700; color:#e2e8f0; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); }
    .so-pager svg { width:16px; height:16px; flex-shrink:0; }
    .so-pager .current { background:#2563eb; border-color:#2563eb; }
    .so-filters { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
    .so-filters a { padding:9px 16px; border-radius:7px; text-decoration:none; color:#fff; background:#1e293b; }
    .so-filters a.active { background:#2563eb; }
</style>

<div class="so-stats">
    <div class="so-stat">
        <span>Awaiting preparation</span>
        <strong>{{ $stats['awaiting_preparation'] }}</strong>
    </div>
    <div class="so-stat">
        <span>Ready for pickup</span>
        <strong>{{ $stats['ready_for_pickup'] }}</strong>
    </div>
    <div class="so-stat">
        <span>Out for delivery</span>
        <strong>{{ $stats['in_delivery'] }}</strong>
    </div>
</div>

<div class="so-filters">
    <a href="{{ route('admin.store-orders.index') }}" class="{{ $status === 'all' ? 'active' : '' }}">All</a>
    @foreach ($statuses as $s)
        <a href="{{ route('admin.store-orders.index', ['status' => $s]) }}" class="{{ $status === $s ? 'active' : '' }}">{{ $statusLabels[$s] }}</a>
    @endforeach
</div>

<div class="so-card">
    <table class="so-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Store items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Preparing by</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                @php($storeLines = $order->items->filter(fn ($item) => $item->isStoreOwned()))
                <tr>
                    <td>
                        <strong>#{{ $order->order_number }}</strong>
                        <div class="so-owner">{{ $order->created_at->format('M d, Y h:i A') }}</div>
                    </td>
                    <td>
                        <strong>{{ $order->user->name ?? $order->shipping_name }}</strong>
                        <div class="so-owner">{{ $order->user->email ?? $order->customer_email }}</div>
                    </td>
                    <td>{{ $storeLines->sum('quantity') }}</td>
                    <td>@orderMoney($order, (float) $order->total)</td>
                    <td><x-order-status-badge :status="$order->status" /></td>
                    <td>
                        @if (in_array($order->status, ['confirmed', 'processing'], true))
                            {{-- Derived from the ACTUAL fulfilment owner of the outstanding
                                 lines — the store's name for its own products, the seller's
                                 name for seller products, both for a mixed order. --}}
                            <span class="so-owner">{{ $order->fulfilmentPreparingLabel() }}</span>
                        @elseif ($order->status === 'ready_for_pickup')
                            <span class="so-owner">Ready for Pickup</span>
                        @else
                            <span class="so-owner">—</span>
                        @endif
                    </td>
                    <td>
                        <a class="so-btn primary" href="{{ route('admin.store-orders.show', $order) }}">Open</a>

                        @if ($order->status === 'confirmed' && $storeLines->contains(fn ($i) => ! $i->isFulfilmentReady()))
                            <form method="POST" action="{{ route('admin.store-orders.status', $order) }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="status" value="processing">
                                <button type="submit" class="so-btn cyan">Start Preparing</button>
                            </form>
                        @endif

                        @if ($order->status === 'processing' && $storeLines->contains(fn ($i) => ! $i->isFulfilmentReady()))
                            <form method="POST" action="{{ route('admin.store-orders.status', $order) }}" style="display:inline;"
                                  onsubmit="return confirm('Mark the store items in order #{{ $order->order_number }} as packed and Ready for Pickup?');">
                                @csrf
                                <input type="hidden" name="status" value="ready_for_pickup">
                                <button type="submit" class="so-btn indigo">Mark Ready for Pickup</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:30px; color:var(--ka-muted);">
                        No orders contain {{ $storeLabel }} products yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($orders->hasPages())
    <div class="so-pager">{{ $orders->links() }}</div>
@endif
@endsection
