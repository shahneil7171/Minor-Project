@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Products Purchased')

@section('content')
    <div class="page-head">
        <div>
            <h2>Products Purchased</h2>
            <p>Best sellers by quantity sold (cancelled orders excluded).</p>
        </div>
        <a class="btn green" href="{{ route('admin.reports.purchased.export') }}">⬇ Export CSV</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Product</th><th class="num">Quantity Sold</th><th class="num">Revenue ({{ app(\App\Services\CurrencyService::class)->symbol() }})</th></tr></thead>
            <tbody>
                @forelse ($products as $i => $product)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $product->product_title }}</strong></td>
                        <td class="num">{{ $product->qty }}</td>
                        <td class="num">@money((float) $product->revenue)</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Nothing sold yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
