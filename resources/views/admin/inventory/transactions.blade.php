@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Inventory Transactions')

@section('content')
    <div class="page-head">
        <div>
            <h2>Inventory transactions</h2>
            <p>Every recorded stock movement, with the level before and after the change.</p>
        </div>
        <a class="btn gray" href="{{ route('admin.inventory.index') }}">← Back to inventory</a>
    </div>

    <form method="GET" action="{{ route('admin.inventory.transactions') }}" class="card" style="padding:14px 18px;">
        <div class="filters" style="margin-bottom:12px;">
            <a href="{{ route('admin.inventory.transactions', ['type' => 'all', 'seller' => $sellerId, 'q' => $search]) }}" class="{{ $type === 'all' ? 'active' : '' }}">All</a>
            @foreach ($types as $key)
                <a href="{{ route('admin.inventory.transactions', ['type' => $key, 'seller' => $sellerId, 'q' => $search]) }}" class="{{ $type === $key ? 'active' : '' }}">{{ $typeLabels[$key] }}</a>
            @endforeach
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <select name="seller" style="flex:1; min-width:180px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
                <option value="0">All sellers</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected($sellerId === $seller->id)>{{ $seller->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Reference or reason" style="flex:1; min-width:200px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
            <button class="btn" type="submit">Filter</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>Seller</th>
                    <th>Type</th>
                    <th class="num">Change</th>
                    <th class="num">Previous</th>
                    <th class="num">New</th>
                    <th>Reference</th>
                    <th>Actor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at?->format('d M Y H:i') }}</td>
                        <td><strong style="color:#fff;">{{ $transaction->product?->title ?? '—' }}</strong></td>
                        <td>{{ $transaction->product_variant_id ?? '—' }}</td>
                        <td>{{ $transaction->seller?->name ?? 'Store' }}</td>
                        <td><span class="badge {{ $transaction->type === 'sale' ? 'processing' : 'active' }}">{{ $transaction->typeLabel() }}</span></td>
                        <td class="num" style="color:{{ $transaction->quantity < 0 ? '#fca5a5' : '#6ee7b7' }}; font-weight:800;">{{ $transaction->signedQuantity() }}</td>
                        <td class="num">{{ $transaction->previous_stock }}</td>
                        <td class="num">{{ $transaction->new_stock }}</td>
                        <td>{{ $transaction->reference ?? '—' }}</td>
                        <td>{{ $transaction->actor?->name ?? 'System' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">No inventory transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $transactions->links() }}</div>
@endsection
