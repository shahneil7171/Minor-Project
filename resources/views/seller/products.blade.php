@extends('layouts.app')

@section('title', 'My Products')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-box-open me-2" style="color:var(--primary-color);"></i>My Products</h1>
        <p class="text-muted mb-0">Products you own. Only you can manage them — other sellers and buyers can still shop them in the storefront.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th><th>SKU</th><th>Category</th><th>Price</th>
                            <th>Stock</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($product->image)
                                            <img src="{{ $product->image }}" alt="{{ $product->title }}" style="width:42px; height:42px; object-fit:contain; border-radius:8px; border:1px solid rgba(0,0,0,0.08); background:#fff; padding:2px;">
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $product->title }}</div>
                                            <div class="small text-muted">/{{ $product->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">{{ $product->sku ?? '—' }}</td>
                                <td class="small">{{ $product->category?->name ?? $product->category_name ?? '—' }}</td>
                                <td class="fw-bold">&#8377;{{ number_format((float) $product->price, 2) }}</td>
                                <td>
                                    <span class="badge {{ $product->quantity > 0 ? 'bg-success' : 'bg-danger' }}">{{ $product->quantity }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $product->status === 1 ? 'bg-primary' : 'bg-secondary' }}">{{ $product->status === 1 ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('product.show', ['product' => $product->slug]) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                        <a href="{{ route('products.edit', ['product' => $product->slug]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('products.destroy', ['product' => $product->slug]) }}" onsubmit="return confirm('Remove this product permanently?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    You have not added any products yet.
                                    <a href="{{ route('products.create') }}" class="d-block mt-2">Add your first product</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
