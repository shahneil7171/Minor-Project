@extends('layouts.app')

@section('title', 'Staff Inventory')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-boxes-stacked me-2" style="color:var(--primary-color);"></i>Inventory Management</h1>
            <p class="text-muted mb-0">Search, filter, and restock existing products. Staff cannot create, edit, or delete products.</p>
        </div>
        <a href="{{ route('staff.inventory.history') }}" class="btn btn-sm btn-primary" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">Stock History</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @error('quantity')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('note')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="row g-3 mb-4">
        @foreach ([
            'Inventory items' => [$stats['total_items'], 'fa-boxes', 'var(--primary-color)'],
            'Units on hand'   => [$stats['total_units'], 'fa-cubes', '#6366f1'],
            'Low stock'       => [$stats['low_stock'], 'fa-triangle-exclamation', '#f59e0b'],
            'Out of stock'    => [$stats['out_of_stock'], 'fa-circle-xmark', '#ef4444'],
        ] as $label => [$value, $icon, $color])
            <div class="col-6 col-md-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:46px; height:46px; background:{{ $color }}18; color:{{ $color }}; flex-shrink:0;">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fw-bold fs-4">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('staff.inventory.index') }}" class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="input-group">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search by product name or SKU">
                <button class="btn btn-primary" type="submit" style="background:linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border:none;">Search</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="text-end">Stock</th>
                        <th>Status</th>
                        <th class="text-end">Restock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if (!empty($row['image']))
                                        <img src="{{ $row['image'] }}" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">
                                    @endif
                                    <span class="fw-semibold">{{ $row['title'] }}</span>
                                </div>
                            </td>
                            <td class="text-muted small">{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['category'] ?? '—' }}</td>
                            <td class="text-end fw-bold">{{ $row['stock'] }}</td>
                            <td>
                                <span class="badge bg-{{ $row['status'] === 'out_of_stock' ? 'danger' : ($row['status'] === 'low_stock' ? 'warning text-dark' : 'success') }}">
                                    {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('staff.inventory.restock', ['product' => $row['product_id']]) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $row['variant_id'] ?? '' }}">
                                    <input type="number" name="quantity" min="1" max="1000000" required class="form-control form-control-sm" style="width:90px;" placeholder="+qty">
                                    <input type="text" name="note" maxlength="255" class="form-control form-control-sm" style="width:140px;" placeholder="Note (optional)">
                                    <button class="btn btn-sm btn-success" type="submit"><i class="fas fa-plus"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No inventory records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rows->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            @if ($rows->onFirstPage())
                <span class="text-muted">Previous</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}" class="btn btn-sm btn-outline-secondary">Previous</a>
            @endif
            <span class="text-muted small">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
            @if ($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary">Next</a>
            @else
                <span class="text-muted">Next</span>
            @endif
        </div>
    @endif
</div>
@endsection