@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Inventory')

@section('content')
    <div class="page-head">
        <div>
            <h2>Inventory</h2>
            <p>Store-wide stock: {{ $stats['total_items'] }} inventory items &middot; low stock when {{ $defaultThreshold }} or fewer units are available.</p>
        </div>
        <a class="btn gray" href="{{ route('admin.inventory.transactions') }}">Inventory transactions</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @error('quantity')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('reason')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="grid-2" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); margin-bottom:20px;">
        <div class="card" style="margin-bottom:0;">
            <h3>Total inventory items</h3>
            <p style="font-size:1.9rem; font-weight:800; color:#fff; margin:0;">{{ $stats['total_items'] }}</p>
            <p style="color:var(--ka-muted); margin:4px 0 0; font-size:.8rem;">{{ $stats['total_units'] }} units on hand</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <h3>Low stock</h3>
            <p style="font-size:1.9rem; font-weight:800; color:#fcd34d; margin:0;">{{ $stats['low_stock'] }}</p>
            <p style="color:var(--ka-muted); margin:4px 0 0; font-size:.8rem;">At or below {{ $defaultThreshold }} available</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <h3>Out of stock</h3>
            <p style="font-size:1.9rem; font-weight:800; color:#fca5a5; margin:0;">{{ $stats['out_of_stock'] }}</p>
            <p style="color:var(--ka-muted); margin:4px 0 0; font-size:.8rem;">Nothing purchasable</p>
        </div>
        <div class="card" style="margin-bottom:0;">
            <h3>Inventory transactions</h3>
            <p style="font-size:1.9rem; font-weight:800; color:#93c5fd; margin:0;">{{ $stats['transactions'] }}</p>
            <p style="color:var(--ka-muted); margin:4px 0 0; font-size:.8rem;">Every recorded stock movement</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.inventory.index') }}" class="card" style="padding:14px 18px;">
        <div class="filters" style="margin-bottom:12px;">
            <a href="{{ route('admin.inventory.index', array_merge(['q' => $search, 'seller' => $sellerId, 'category' => $categoryId], ['status' => 'all'])) }}" class="{{ $status === 'all' ? 'active' : '' }}">All ({{ $counts['all'] }})</a>
            <a href="{{ route('admin.inventory.index', array_merge(['q' => $search, 'seller' => $sellerId, 'category' => $categoryId], ['status' => 'in_stock'])) }}" class="{{ $status === 'in_stock' ? 'active' : '' }}">{{ $statusLabels['in_stock'] }} ({{ $counts['in_stock'] }})</a>
            <a href="{{ route('admin.inventory.index', array_merge(['q' => $search, 'seller' => $sellerId, 'category' => $categoryId], ['status' => 'low_stock'])) }}" class="{{ $status === 'low_stock' ? 'active' : '' }}">{{ $statusLabels['low_stock'] }} ({{ $counts['low_stock'] }})</a>
            <a href="{{ route('admin.inventory.index', array_merge(['q' => $search, 'seller' => $sellerId, 'category' => $categoryId], ['status' => 'out_of_stock'])) }}" class="{{ $status === 'out_of_stock' ? 'active' : '' }}">{{ $statusLabels['out_of_stock'] }} ({{ $counts['out_of_stock'] }})</a>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <select name="seller" class="field-select" style="flex:1; min-width:170px;">
                <option value="0">All sellers</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected($sellerId === $seller->id)>{{ $seller->name }}</option>
                @endforeach
            </select>
            <select name="category" style="flex:1; min-width:170px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <option value="0">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Product or SKU" style="flex:1; min-width:180px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
            <button class="btn" type="submit">Filter</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Seller</th>
                    <th>SKU</th>
                    <th>Variant</th>
                    <th class="num">Stock</th>
                    <th class="num">Reserved</th>
                    <th class="num">Available</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $badgeClass = $row['status'] === 'out_of_stock'
                            ? 'rejected'
                            : ($row['status'] === 'low_stock' ? 'pending' : 'active');
                    @endphp
                    <tr>
                        <td><strong style="color:#fff;">{{ $row['title'] }}</strong></td>
                        <td>{{ $row['product']->seller?->name ?? 'Store' }}</td>
                        <td>{{ $row['sku'] ?? '—' }}</td>
                        <td>{{ $row['variant'] ?? '—' }}</td>
                        <td class="num">{{ $row['stock'] }}</td>
                        <td class="num">{{ $row['reserved'] }}</td>
                        <td class="num"><strong>{{ $row['available'] }}</strong></td>
                        <td><span class="badge {{ $badgeClass }}">{{ $row['status_label'] }}</span></td>
                        <td>{{ $row['updated_at']?->format('d M Y H:i') ?? '—' }}</td>
                        <td>
                            <details>
                                <summary class="row-actions" style="display:inline-block; cursor:pointer; list-style:none;">
                                    <span class="primary">Adjust stock</span>
                                </summary>
                                <form method="POST" action="{{ route('admin.inventory.adjust', ['product' => $row['product_id']]) }}" style="margin-top:8px; min-width:240px;">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $row['variant_id'] }}">
                                    <div class="field">
                                        <label>Adjustment (e.g. +5 or -2)</label>
                                        <input type="number" step="1" name="quantity" required>
                                    </div>
                                    <div class="field">
                                        <label>Reason (required)</label>
                                        <input type="text" name="reason" maxlength="255" required placeholder="Physical stock received">
                                    </div>
                                    <button class="btn" type="submit">Save adjustment</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">No inventory records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($inventory->hasPages())
        <div class="pagination">
            @if ($inventory->onFirstPage())
                <span>Previous</span>
            @else
                <a href="{{ $inventory->previousPageUrl() }}">Previous</a>
            @endif
            <span class="current">Page {{ $inventory->currentPage() }} of {{ $inventory->lastPage() }}</span>
            @if ($inventory->hasMorePages())
                <a href="{{ $inventory->nextPageUrl() }}">Next</a>
            @else
                <span>Next</span>
            @endif
        </div>
    @endif

    <div class="card" style="margin-top:24px;">
        <h3>Recent inventory transactions</h3>
        @forelse ($recent as $transaction)
            <p style="margin:0 0 8px; color:var(--ka-muted); font-size:.86rem;">
                <span class="badge {{ $transaction->type === 'sale' ? 'processing' : 'active' }}">{{ $transaction->typeLabel() }}</span>
                <strong style="color:#fff;">{{ $transaction->product?->title ?? '—' }}</strong>
                {{ $transaction->signedQuantity() }}
                ({{ $transaction->previous_stock }} &rarr; {{ $transaction->new_stock }})
                @if ($transaction->reference)<span> &middot; {{ $transaction->reference }}</span>@endif
                <span> &middot; {{ $transaction->created_at?->format('d M Y H:i') }}</span>
            </p>
        @empty
            <p class="empty" style="padding:0;">No inventory transactions yet.</p>
        @endforelse
        <a class="btn gray" href="{{ route('admin.inventory.transactions') }}">View all transactions</a>
    </div>
@endsection
