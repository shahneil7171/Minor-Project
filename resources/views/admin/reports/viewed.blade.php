@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Products Viewed')

@section('content')
    <div class="page-head">
        <div>
            <h2>Products Viewed</h2>
            <p>Product detail page views, most viewed first.</p>
        </div>
        <a class="btn green" href="{{ route('admin.reports.viewed.export') }}">⬇ Export CSV</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Product</th><th>Slug</th><th class="num">Views</th></tr></thead>
            <tbody>
                @forelse ($views as $i => $view)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><strong>{{ $view->title ?? $view->product_slug }}</strong></td>
                        <td style="color:var(--ka-muted);">{{ $view->product_slug }}</td>
                        <td class="num">{{ number_format($view->views) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No product views tracked yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
